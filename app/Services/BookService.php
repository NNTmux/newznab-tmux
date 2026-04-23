<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use App\Models\BookInfo;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Services\NameFixing\Extractors\ObfuscatedSubjectExtractor;
use App\Services\Releases\ReleaseBrowseService;
use App\Support\BookMatchScorer;
use App\Support\DTOs\BookParseResult;
use App\Support\MetadataSearchLookup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service class for book data fetching and processing.
 */
class BookService
{
    public bool $echooutput;

    public int $bookqty;

    public int $sleeptime;

    public string $imgSavePath;

    public string $renamed;

    public ?string $parsedIsbn;

    public ?BookParseResult $parsedBookResult;

    private ObfuscatedSubjectExtractor $obfuscatedSubjectExtractor;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echooutput = config('nntmux.echocli');

        $this->bookqty = Settings::settingValue('maxbooksprocessed') !== '' ? (int) Settings::settingValue('maxbooksprocessed') : 300;
        $this->sleeptime = Settings::settingValue('amazonsleep') !== '' ? (int) Settings::settingValue('amazonsleep') : 1000;
        $this->imgSavePath = storage_path('covers/book/');

        $this->renamed = (int) Settings::settingValue('lookupbooks') === 2 ? 'AND isrenamed = 1' : '';

        $this->parsedIsbn = null;
        $this->parsedBookResult = null;
        $this->obfuscatedSubjectExtractor = new ObfuscatedSubjectExtractor;
    }

    /**
     * Get book info by ID.
     */
    public function getBookInfo(?int $id): ?Model
    {
        if ($id === null) {
            return null;
        }

        return BookInfo::query()->where('id', $id)->first();
    }

    /**
     * Get book info by name using full-text search.
     */
    public function getBookInfoByName(string $title, ?BookParseResult $parsed = null): ?Model
    {
        $parsed ??= $this->parsedBookResult;
        $searchWords = '';
        $title = preg_replace(['/( - | -|\(.+\)|\(|\))/', '/[^\w ]+/'], [' ', ''], $title);
        $title = trim(trim(preg_replace('/\s\s+/i', ' ', $title)));
        foreach (explode(' ', $title) as $word) {
            $word = trim(rtrim(trim($word), '-'));
            if ($word !== '' && $word !== '-') {
                $word = '+'.$word;
                $searchWords .= sprintf('%s ', $word);
            }
        }
        $searchWords = trim($searchWords);

        if (Search::isAvailable()) {
            $q = MetadataSearchLookup::normalizeBooleanSearchWords($searchWords);
            if ($q !== '') {
                $hits = Search::searchSecondary(SecondarySearchIndex::Books, $q, 25);
                $bookIds = array_values(array_map('intval', $hits['id'] ?? []));
                if ($bookIds !== []) {
                    $rowsById = BookInfo::query()
                        ->whereIn('id', $bookIds)
                        ->get()
                        ->keyBy('id');
                    $best = $this->pickBestExistingBook($rowsById->values()->all(), $parsed);
                    if ($best !== null) {
                        return $best;
                    }
                }
            }

            return null;
        }

        $results = BookInfo::query()
            ->whereRaw('MATCH (author, title) AGAINST (? IN BOOLEAN MODE)', [$searchWords])
            ->limit(25)
            ->get();

        return $this->pickBestExistingBook($results->all(), $parsed);
    }

    /**
     * Get book range with pagination.
     *
     * @param  array<int|string, mixed>  $cat  Category IDs (list or associative)
     * @param  array<string, mixed>  $excludedCats
     */
    public function getBookRange(int $page, array $cat, int $start, int $num, string $orderBy, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $useIndexForAuthorTitle = Search::isAvailable()
            && (! empty($_REQUEST['author']) || ! empty($_REQUEST['title']));
        $bookIdsFromSearch = null;
        if ($useIndexForAuthorTitle) {
            $q = trim(
                stripslashes((string) ($_REQUEST['author'] ?? '')).' '
                .stripslashes((string) ($_REQUEST['title'] ?? ''))
            );
            if ($q === '') {
                $bookIdsFromSearch = [];
            } else {
                $bookIdsFromSearch = Search::searchSecondary(SecondarySearchIndex::Books, $q, 5000)['id'];
            }
            if ($bookIdsFromSearch === []) {
                return collect();
            }
        }

        $browseby = $this->getBrowseBy($useIndexForAuthorTitle);
        $bookInClause = '';
        if (is_array($bookIdsFromSearch) && $bookIdsFromSearch !== []) {
            $bookInClause = ' AND boo.id IN ('.implode(',', array_map('intval', $bookIdsFromSearch)).')';
        }
        $catsrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        $exccatlist = '';
        if (\count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getBookOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $showPasswords = app(ReleaseBrowseService::class)->showPasswords();

        $baseWhere = "boo.cover = 1 AND boo.title != '' "
            ."AND r.passwordstatus {$showPasswords} "
            .$browseby.' '
            .$bookInClause.' '
            .$catsrch.' '
            .$exccatlist;

        $cacheKey = md5('book_range_'.$baseWhere.$order[0].$order[1].$start.$num.$page);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Step 1: Count total distinct books matching filters
        $countSql = 'SELECT COUNT(DISTINCT boo.id) AS total '
            .'FROM bookinfo boo '
            .'INNER JOIN releases r ON boo.id = r.bookinfo_id '
            .'WHERE '.$baseWhere;

        $totalResult = DB::select($countSql);
        $totalCount = $totalResult[0]->total ?? 0;

        if ($totalCount === 0) {
            return collect();
        }

        // Step 2: Get paginated book entity list with only needed columns
        $bookSql = 'SELECT boo.id, boo.title, boo.author, boo.cover, boo.publisher, boo.publishdate, boo.overview, boo.url, '
            .'MAX(r.postdate) AS latest_postdate, '
            .'COUNT(r.id) AS total_releases '
            .'FROM bookinfo boo '
            .'INNER JOIN releases r ON boo.id = r.bookinfo_id '
            .'WHERE '.$baseWhere.' '
            .'GROUP BY boo.id, boo.title, boo.author, boo.cover, boo.publisher, boo.publishdate, boo.overview, boo.url '
            ."ORDER BY {$order[0]} {$order[1]} "
            ."LIMIT {$num} OFFSET {$start}";

        $books = BookInfo::fromQuery($bookSql);

        if ($books->isEmpty()) {
            return collect();
        }

        // Build list of book IDs for release query
        $bookIds = $books->pluck('id')->toArray();
        $inBookIds = implode(',', array_map('intval', $bookIds));

        // Step 3: Get top 2 releases per book using ROW_NUMBER()
        $releasesSql = 'SELECT ranked.id, ranked.bookinfo_id, ranked.guid, ranked.searchname, '
            .'ranked.size, ranked.postdate, ranked.adddate, ranked.haspreview, ranked.grabs, '
            .'ranked.comments, ranked.totalpart, ranked.group_name, ranked.nfoid, ranked.failed_count '
            .'FROM ( '
            .'SELECT r.id, r.bookinfo_id, r.guid, r.searchname, r.size, r.postdate, r.adddate, '
            .'r.haspreview, r.grabs, r.comments, r.totalpart, g.name AS group_name, '
            .'rn.releases_id AS nfoid, df.failed AS failed_count, '
            .'ROW_NUMBER() OVER (PARTITION BY r.bookinfo_id ORDER BY r.postdate DESC) AS rn '
            .'FROM releases r '
            .'LEFT JOIN usenet_groups g ON g.id = r.groups_id '
            .'LEFT JOIN release_nfos rn ON rn.releases_id = r.id '
            .'LEFT JOIN dnzb_failures df ON df.release_id = r.id '
            ."WHERE r.bookinfo_id IN ({$inBookIds}) "
            ."AND r.passwordstatus {$showPasswords} "
            .$catsrch.' '
            .$exccatlist
            .') ranked '
            .'WHERE ranked.rn <= 2 '
            .'ORDER BY ranked.bookinfo_id, ranked.postdate DESC';

        $releases = DB::select($releasesSql);

        // Group releases by bookinfo_id for fast lookup
        $releasesByBook = [];
        foreach ($releases as $release) {
            $releasesByBook[$release->bookinfo_id][] = $release;
        }

        // Attach releases to each book entity
        foreach ($books as $book) {
            $book->releases = $releasesByBook[$book->id] ?? []; // @phpstan-ignore assign.propertyReadOnly
        }

        // Set total count on first item
        if ($books->isNotEmpty()) {
            $books[0]->_totalcount = $totalCount; // @phpstan-ignore property.notFound
        }

        Cache::put($cacheKey, $books, $expiresAt);

        return $books;
    }

    /**
     * Get book order array.
     *
     * @return array<string, mixed>
     */
    /**
     * @return array{0: string, 1: string}
     */
    public function getBookOrder(string $orderBy): array
    {
        $order = $orderBy === '' ? 'r.postdate' : $orderBy;
        $orderArr = explode('_', $order);
        $orderfield = match ($orderArr[0]) {
            'title' => 'boo.title',
            'author' => 'boo.author',
            'publishdate' => 'boo.publishdate',
            'size' => 'r.size',
            'files' => 'r.totalpart',
            'stats' => 'r.grabs',
            default => 'r.postdate',
        };
        $ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderfield, $ordersort];
    }

    /**
     * Get book ordering options.
     *
     * @return array<int, string>
     */
    public function getBookOrdering(): array
    {
        return [
            'title_asc',
            'title_desc',
            'posted_asc',
            'posted_desc',
            'size_asc',
            'size_desc',
            'files_asc',
            'files_desc',
            'stats_asc',
            'stats_desc',
            'releasedate_asc',
            'releasedate_desc',
            'author_asc',
            'author_desc',
        ];
    }

    /**
     * Get browse by options.
     *
     * @return array<string, mixed>
     */
    public function getBrowseByOptions(): array
    {
        return ['author' => 'author', 'title' => 'title'];
    }

    /**
     * Get browse by SQL clause.
     */
    public function getBrowseBy(bool $skipAuthorTitleLike = false): string
    {
        $browseby = ' ';
        foreach ($this->getBrowseByOptions() as $bbk => $bbv) {
            if ($skipAuthorTitleLike && ($bbk === 'author' || $bbk === 'title')) {
                continue;
            }
            if (! empty($_REQUEST[$bbk])) {
                $bbs = stripslashes($_REQUEST[$bbk]);
                $browseby .= ' AND boo.'.$bbv.' '.'LIKE '.escapeString('%'.$bbs.'%');
            }
        }

        return $browseby;
    }

    /**
     * Update book by ID.
     */
    public function update(
        int $id,
        string $title,
        ?string $asin,
        ?string $url,
        ?string $author,
        ?string $publisher,
        mixed $publishdate,
        int $cover
    ): bool {
        return BookInfo::query()->where('id', $id)->update([
            'title' => $title,
            'asin' => $asin,
            'url' => $url,
            'author' => $author,
            'publisher' => $publisher,
            'publishdate' => $publishdate,
            'cover' => $cover,
        ]) > 0;
    }

    /**
     * Process book releases, 1 category at a time.
     *
     * @throws \Exception
     */
    public function processBookReleases(string $groupID = '', string $guidChar = ''): void
    {
        $this->normalizeBookSearchNames($groupID, $guidChar);

        $query = Release::query()
            ->whereNull('bookinfo_id')
            ->where(static function ($builder): void {
                $builder->whereBetween('categories_id', [Category::BOOKS_ROOT, Category::BOOKS_UNKNOWN])
                    ->orWhere('categories_id', Category::MUSIC_AUDIOBOOK);
            })
            ->orderByDesc('postdate')
            ->limit($this->bookqty);

        if ($guidChar !== '') {
            $query->where('leftguid', 'like', $guidChar.'%');
        }

        if ($groupID !== '') {
            $query->where('groups_id', $groupID);
        }

        $this->processBookReleasesHelper(
            $query->get(['searchname', 'id', 'categories_id']),
            sprintf(
                '%d-%d,%d',
                Category::BOOKS_ROOT,
                Category::BOOKS_UNKNOWN,
                Category::MUSIC_AUDIOBOOK
            )
        );
    }

    protected function normalizeBookSearchNames(string $groupID = '', string $guidChar = ''): void
    {
        $query = Release::query()
            ->select(['id', 'name', 'searchname', 'categories_id', 'isrenamed'])
            ->where(static function ($builder): void {
                $builder->whereBetween('categories_id', [Category::BOOKS_ROOT, Category::BOOKS_UNKNOWN])
                    ->orWhere('categories_id', Category::MUSIC_AUDIOBOOK);
            })
            ->where(function ($builder): void {
                $builder->where('isrenamed', 0)
                    ->orWhere('searchname', 'like', 'N:/NZB%')
                    ->orWhere('searchname', 'like', 'N_NZB_%')
                    ->orWhere('name', 'like', 'N:/NZB%')
                    ->orWhere('name', 'like', 'N_NZB_%');
            })
            ->orderByDesc('postdate')
            ->limit($this->bookqty);

        if ($guidChar !== '') {
            $query->where('leftguid', 'like', $guidChar.'%');
        }

        if ($groupID !== '') {
            $query->where('groups_id', $groupID);
        }

        foreach ($query->get() as $release) {
            $releaseType = (int) $release->categories_id === Category::MUSIC_AUDIOBOOK ? 'audiobook' : 'ebook';
            $sourceName = $this->preferredBookSourceName((string) $release->searchname, (string) $release->name);
            $parsed = $this->parseReleaseName($sourceName, $releaseType);
            $normalizedSearchName = $this->determineReadableBookSearchName($sourceName, $parsed);

            if ($normalizedSearchName === null || $normalizedSearchName === $release->searchname) {
                continue;
            }

            Release::query()->where('id', (int) $release->id)->update([
                'searchname' => $normalizedSearchName,
                'isrenamed' => 1,
            ]);
            Search::updateRelease((int) $release->id);
        }
    }

    /**
     * Process book releases helper.
     *
     * @throws \Exception
     */
    protected function processBookReleasesHelper(mixed $res, mixed $categoryID): void
    {
        if ($res->count() > 0) {
            if ($this->echooutput) {
                cli()->header('Processing '.$res->count().' book release(s) for categories id '.$categoryID);
            }

            $bookId = -2;
            foreach ($res as $arr) {
                $startTime = now()->timestamp;
                $usedExternalApi = false;
                // audiobooks are also books and should be handled in an identical manor, even though it falls under a music category
                if ($arr['categories_id'] === (int) Category::MUSIC_AUDIOBOOK) {
                    // audiobook
                    $bookInfo = $this->parseTitle($arr['searchname'], $arr['id'], 'audiobook');
                } else {
                    // ebook
                    $bookInfo = $this->parseTitle($arr['searchname'], $arr['id'], 'ebook');
                }

                if ($bookInfo !== false) {
                    if ($this->echooutput) {
                        cli()->info('Looking up: '.$bookInfo);
                    }

                    // Do a local lookup first
                    $bookCheck = $this->getBookInfoByName($bookInfo, $this->parsedBookResult);
                    $failCacheKey = 'book_lookup_fail_'.md5(strtolower($bookInfo));

                    if ($bookCheck === null && Cache::has($failCacheKey)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echooutput) {
                            cli()->info('Cached previous failure. Skipping.');
                        }
                        $bookId = -2;
                    } elseif ($bookCheck === null) {
                        $bookId = $this->updateBookInfo($bookInfo, $this->parsedIsbn);
                        $usedExternalApi = true;
                        if ($bookId === -2) {
                            Cache::put($failCacheKey, true, now()->addDays(7));
                        } else {
                            Cache::forget($failCacheKey);
                        }
                    } else {
                        $bookId = $bookCheck['id'];
                    }

                    // Update release.
                    Release::query()->where('id', $arr['id'])->update(['bookinfo_id' => $bookId]);
                } else { // Could not parse release title.
                    Release::query()->where('id', $arr['id'])->update(['bookinfo_id' => $bookId]);
                    if ($this->echooutput) {
                        echo '.';
                    }
                }
                // Sleep to avoid flooding external book metadata providers.
                $diff = floor((now()->timestamp - $startTime) * 1000000);
                if ($this->sleeptime * 1000 - $diff > 0 && $usedExternalApi === true) {
                    usleep((int) ($this->sleeptime * 1000 - $diff));
                }
            }
        } elseif ($this->echooutput) {
            cli()->header('No book releases to process for categories id '.$categoryID);
        }
    }

    /**
     * Parse release title.
     *
     * @return bool|string
     */
    public function parseTitle(mixed $release_name, mixed $releaseID, mixed $releasetype)
    {
        $rawReleaseName = (string) $release_name;
        $parsed = $this->parseReleaseName($rawReleaseName, (string) $releasetype);
        $normalizedReleaseName = $this->determineReadableBookSearchName($rawReleaseName, $parsed) ?? $rawReleaseName;
        if ($normalizedReleaseName !== $rawReleaseName) {
            Release::query()->where('id', (int) $releaseID)->update([
                'searchname' => $normalizedReleaseName,
                'isrenamed' => 1,
            ]);
            Search::updateRelease((int) $releaseID);
        }
        $this->parsedBookResult = $parsed;
        $this->parsedIsbn = $parsed->isbn;
        $releasename = $parsed->title;

        // the default existing type was ebook, this handles that in the same manor as before
        if ($releasetype === 'ebook') {
            if ($parsed->isJunk) {
                if ($this->echooutput) {
                    cli()->headerOver('Changing category to misc books: ').cli()->primary($releasename);
                }
                Release::query()->where('id', $releaseID)->update(['categories_id' => Category::BOOKS_UNKNOWN]);
                Search::updateRelease((int) $releaseID);

                return false;
            }

            if ($parsed->isMagazine) {
                if ($this->echooutput) {
                    cli()->headerOver('Changing category to magazines: ').cli()->primary($releasename);
                }
                Release::query()->where('id', $releaseID)->update(['categories_id' => Category::BOOKS_MAGAZINES]);
                Search::updateRelease((int) $releaseID);

                return false;
            }
            if (! empty($releasename) && ! preg_match('/^[a-z0-9]+$|^([0-9]+ ){1,}$|Part \d+/i', $releasename)) {
                $wordCount = count(preg_split('/\s+/', trim($releasename)) ?: []);
                if ($wordCount >= 2) {
                    return $parsed->searchQuery();
                }
            }

            return false;
        }
        if ($releasetype === 'audiobook') {
            if ($parsed->isJunk) {
                return false;
            }

            if (! empty($releasename) && ! preg_match('/^[a-z0-9]+$|^([0-9]+ ){1,}$|Part \d+/i', $releasename)) {
                $wordCount = count(preg_split('/\s+/', trim($releasename)) ?: []);
                if ($wordCount >= 2) {
                    return $parsed->searchQuery();
                }
            }

            return false;
        }

        return false;
    }

    public function parseReleaseName(string $releaseName, string $releaseType = 'ebook'): BookParseResult
    {
        $rawReleaseName = $releaseName;
        $releaseName = $this->obfuscatedSubjectExtractor->extract($releaseName) ?? $releaseName;
        if (preg_match('/"([^"]{3,240})"/', $releaseName, $quotedMatch) === 1) {
            $releaseName = $quotedMatch[1];
        }
        $isbn = $this->extractIsbn($releaseName);
        $a = preg_replace('/\d{1,2} \d{1,2} \d{2,4}|(19|20)\d\d|anybody got .+?[a-z]\? |[ ._-](Novel|TIA)([ ._-]|$)|([ \.])HQ([-\. ])|[\(\)\.\-_ ](AVI|AZW3?|DOC|EPUB|LIT|MOBI|NFO|RETAIL|(si)?PDF|RTF|TXT)[\)\]\.\-_ ](?![a-z0-9])|compleet|DAGSTiDNiNGEN|DiRFiX|\+ extra|r?e ?Books?([\.\-_ ]English|ers)?|azw3?|ePu([bp])s?|html|mobi|^NEW[\.\-_ ]|PDF([\.\-_ ]English)?|Please post more|Post description|Proper|Repack(fix)?|[\.\-_ ](Chinese|English|French|German|Italian|Retail|Scan|Swedish|Multilingual)|^R4 |Repost|Skytwohigh|TIA!+|TruePDF|V413HAV|(would someone )?please (re)?post.+? "|with the authors name right/i', '', $releaseName);
        $b = preg_replace('/^(As Req |conversion |eq |Das neue Abenteuer \d+|Fixed version( ignore previous post)?|Full |Per Req As Found|(\s+)?R4 |REQ |revised |version |\d+(\s+)?$)|(COMPLETE|INTERNAL|RELOADED| (AZW3|eB|docx|ENG?|exe|FR|Fix|gnv64|MU|NIV|R\d\s+\d{1,2} \d{1,2}|R\d|Req|TTL|UC|v(\s+)?\d))(\s+)?$/i', '', (string) $a);
        $c = preg_replace('/ - \[.+\]|\[.+\]/', '', (string) $b);
        $d = preg_replace('/(\(\)|\[\])/', '', (string) $c);
        $normalized = trim((string) preg_replace('/\s\s+/i', ' ', (string) $d));
        $normalized = (string) preg_replace('/[._]+/', ' ', $normalized);
        $normalized = (string) preg_replace('/\b97[89](?:[-\s]?\d){10}\b|\b\d(?:[-\s]?\d){8}[-\s]?[\dXx]\b/', '', $normalized);
        $normalized = (string) preg_replace('/\b(S\d{1,3}E\d{1,3}|Season\s*\d+|Temporada\s*\d+|Saison\s*\d+|Staffel\s*\d+|Episode\s*\d+|HDTV|WEB[\s-]?DL|WEBRip|BluRay|BDRip|DVDRip|BRRip|XviD|[xh][\s.]?26[45]|HEVC|10bit|1080[pi]|720p|2160p|4K|UHD|HDR|REMUX|AAC5?\s*\d|DDP?5\s*\d|ATMOS|PAR2?|vol\d+\+\d+|NTb|FLUX|PSA|RARBG|YTS|YIFY|AMZN|DSNP|NF|ATVP|HMAX)\b/i', '', $normalized);
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));

        $year = null;
        $specialMagazineTitle = null;
        if (preg_match('/^MCN[._ -](?<month>[A-Za-z]+)[._ -](?<day>\d{1,2})[._ -](?<year>(?:19|20)\d{2})/i', $rawReleaseName, $mcnMatch) === 1) {
            $month = ucfirst(strtolower($mcnMatch['month']));
            $day = ltrim($mcnMatch['day'], '0');
            $specialMagazineTitle = sprintf('MCN - %s %s, %s', $month, $day === '' ? $mcnMatch['day'] : $day, $mcnMatch['year']);
            $year = (int) $mcnMatch['year'];
        }
        if (preg_match('/\b(19|20)\d{2}\b/', $normalized, $yearMatch) === 1) {
            $year = (int) $yearMatch[0];
        }

        $format = null;
        if (preg_match('/\b(EPUB|MOBI|AZW3?|PDF|FB2|DJVU|LIT)\b/i', $releaseName, $formatMatch) === 1) {
            $format = strtoupper($formatMatch[1]);
        }

        $author = null;
        $title = $specialMagazineTitle ?? $normalized;
        if ($specialMagazineTitle === null && preg_match('/^(?<author>[A-Za-z0-9&\'\.\-\s]{3,80})\s-\s(?<title>.+)$/', $normalized, $matches) === 1) {
            $candidateAuthor = trim($matches['author']);
            $candidateTitle = trim($matches['title']);
            $isMcnMagazineSplit = preg_match('/^MCN$/i', $candidateAuthor) === 1
                && preg_match('/^\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\w*\b/i', $candidateTitle) === 1;
            if ($isMcnMagazineSplit === false && preg_match('/^(Issue\s+\d+|\d+\w*\s+Edition\b)/i', $candidateTitle) !== 1) {
                $author = $candidateAuthor;
                $title = $candidateTitle;
            }
        } elseif (preg_match('/^(?<author>[A-Za-z0-9&\'\.\-\s]{3,80})\s+by\s+(?<title>.+)$/i', $normalized, $matches) === 1) {
            $author = trim($matches['author']);
            $title = trim($matches['title']);
        } elseif (preg_match('/^(?<title>.+?)\s+by\s+(?<author>[A-Za-z0-9&\'\.\-\s]{3,80})$/i', $normalized, $matches) === 1) {
            $author = trim($matches['author']);
            $title = trim($matches['title']);
        }

        $title = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/\((Book|Series)\s*\d+\)/i', '', $title)));
        if ($author === null && preg_match('/^(?<title>.+?)[\s._-]+(?<year>(19|20)\d{2})[\s._-]+(?<format>EPUB|MOBI|AZW3?|PDF|FB2|DJVU|LIT)$/i', $normalized, $matches) === 1) {
            $title = trim(str_replace(['.', '_'], ' ', $matches['title']));
        }
        if ($author === null && preg_match('/^(?<title>.+?)\s+Vol\.?\s*\d{1,3}$/i', $title, $matches) === 1) {
            $title = trim($matches['title']);
        }

        $isJunk = false;
        $junkPattern = '/^([a-z0-9] )+$|ArtofUsenet|ekiosk|(ebook|mobi).+collection|erotica|Full Video|ImwithJamie|linkoff org|Mega.+pack|^[a-z0-9]+ (?!((January|February|March|April|May|June|July|August|September|O([ck])tober|November|De([cz])ember)))[a-z]+( (ebooks?|The))?$|NY Times|(Book|Massive) Dump|Sexual/i';
        if (preg_match($junkPattern, $normalized) === 1) {
            $isJunk = true;
        }
        if ($releaseType === 'ebook' && preg_match('/\b(v?\d+\.\d+\.\d+(?:\.\d+)?|x64|x86|portable|setup|crack(ed)?|patch)\b/i', $releaseName) === 1) {
            $isJunk = true;
        }
        if ($releaseType === 'ebook' && preg_match('/\b(WEB[\.\-_ ]?FLAC|MP3|320kbps|FALCON|discography)\b/i', $releaseName) === 1) {
            $isJunk = true;
        }
        if (preg_match('/\b(S\d{1,3}[\.\-_ ]?E\d{1,3}|Season[\.\-_ ]?\d+|Temporada[\.\-_ ]?\d+|Saison[\.\-_ ]?\d+|Staffel[\.\-_ ]?\d+|Episode[\.\-_ ]?\d+|[12]\d{3}[\.\-_ ]?S\d{2}|HDTV|WEB[\.\-_ ]?DL|WEBRip|BluRay|BDRip|BRRip|DVDRip|XviD|x26[45]|H[\.\-_ ]?26[45]|HEVC|10bit|AAC5[\.\-]1|DDP?5[\.\-]1|ATMOS|NTb|FLUX|PSA|RARBG|YTS|YIFY|AMZN|DSNP|HMAX|NF[\.\-_ ]|ATVP)\b/i', $releaseName) === 1) {
            $isJunk = true;
        }
        if (preg_match('/\b(PAR2|PAR[\.\-_ ]?Files?|vol\d+\+\d+|\.nzb|\.part\d+\.rar|\.r\d{2,}|sample|subs?pack|subtitle|nfo[\.\-_ ]?only)\b/i', $releaseName) === 1) {
            $isJunk = true;
        }
        if (preg_match('/\b(1080[pi]|720p|2160p|4K[\.\-_ ]?UHD|UHD|HDR|SDR|REMUX|mHD|mSD)\b/i', $releaseName) === 1) {
            $isJunk = true;
        }
        if ($releaseType === 'ebook' && preg_match('/\b(keygen|activat(or|ion)|licen[sc]e[\.\-_ ]?key|serial[\.\-_ ]?number|nulled|warez|regged|incl[\.\-_ ]?crack)\b/i', $releaseName) === 1) {
            $isJunk = true;
        }

        $isMagazine = (
            preg_match('/^([a-z0-9ü!]+ ){1,2}(N|Vol)?\d{1,4}([abc])?$|^([a-z0-9]+ ){1,2}(Jan( |unar|$)|Feb( |ruary|$)|Mar( |ch|$)|Apr( |il|$)|May(?![a-z0-9])|Jun([ e$])|Jul([ y$])|Aug( |ust|$)|Sep( |tember|$)|O([ck])t( |ober|$)|Nov( |ember|$)|De([cz])( |ember|$))/ui', $normalized) === 1
            || preg_match('/\bIssue[\._\- ]?\d{1,4}\b.*\b(19|20)\d{2}\b/i', $normalized) === 1
            || preg_match('/\bIssue[\._\- ]?\d{1,4}\b/i', $normalized) === 1
            || (preg_match('/\bMCN[._ -]/i', $rawReleaseName) === 1
                && preg_match('/\bMAGAZINE\b/i', $rawReleaseName) === 1)
            || (preg_match('/^MCN\b/i', $normalized) === 1
                && preg_match('/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\w*\b/i', $normalized) === 1
                && preg_match('/\b\d{1,2}\b/', $normalized) === 1)
        ) && preg_match('/Part \d+/i', $normalized) !== 1;

        return new BookParseResult(
            rawName: $releaseName,
            title: $title,
            author: $author,
            isbn: $isbn,
            year: $year,
            format: $format,
            isJunk: $isJunk,
            isMagazine: $isMagazine,
        );
    }

    public function extractIsbn(string $releaseName): ?string
    {
        if (preg_match('/\b(97[89](?:[-\s]?\d){10})\b/', $releaseName, $matches) === 1) {
            $isbn = preg_replace('/[^0-9]/', '', $matches[1]);
            if (is_string($isbn) && strlen($isbn) === 13) {
                return $isbn;
            }
        }

        if (preg_match('/\b(\d(?:[-\s]?\d){8}[-\s]?[\dXx])\b/', $releaseName, $matches) === 1) {
            $isbn = strtoupper((string) preg_replace('/[^0-9X]/i', '', $matches[1]));
            if (strlen($isbn) === 10) {
                return $isbn;
            }
        }

        return null;
    }

    protected function determineReadableBookSearchName(string $rawReleaseName, BookParseResult $parsed): ?string
    {
        if ($this->looksLikeObfuscatedBookSubject($rawReleaseName)) {
            $normalized = $this->obfuscatedSubjectExtractor->extract($rawReleaseName);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        if ($parsed->isJunk) {
            return null;
        }

        $candidate = trim($parsed->searchQuery());
        if ($candidate === '' || preg_match('/^[a-z0-9]+$|^([0-9]+ ){1,}$|Part \d+/i', $candidate)) {
            return null;
        }

        $wordCount = count(preg_split('/\s+/', $candidate) ?: []);

        return $wordCount >= 2 ? $candidate : null;
    }

    protected function preferredBookSourceName(string $searchName, string $originalName): string
    {
        return trim($searchName) !== '' ? $searchName : $originalName;
    }

    protected function looksLikeObfuscatedBookSubject(string $value): bool
    {
        return preg_match('/^\s*(?:N:\/NZB\b|N[\s._:-]*NZB[\s._-]*\[|(?:re\s*)?posted\s+by\b)/i', $value) === 1;
    }

    /**
     * Update book info from external sources.
     *
     * @return false|int|string
     *
     * @throws \Exception
     */
    public function updateBookInfo(string $bookInfo = '', ?string $isbn = null)
    {
        $ri = new ReleaseImageService;
        $isbnDb = new IsbnDbService;
        $googleBooks = new GoogleBooksService;
        $openLibrary = new OpenLibraryService;
        $itunes = new ItunesService;
        $scorer = new BookMatchScorer;

        $bookId = -2;
        $book = null;
        if ($bookInfo !== '') {
            $wordCount = count(array_filter(preg_split('/\s+/', trim($bookInfo)) ?: []));
            if ($wordCount < 3) {
                return -2;
            }

            $parsed = $this->parsedBookResult ?? $this->parseReleaseName($bookInfo);
            $candidates = [];
            $resolvedSource = null;
            if ($isbnDb->isConfigured()) {
                if ($isbn !== null && $isbn !== '') {
                    cli()->info('Fetching data from ISBNdb by ISBN '.$isbn);
                    $candidate = $isbnDb->findByIsbn($isbn);
                    if (is_array($candidate)) {
                        $candidates[] = $candidate;
                        $resolvedSource = 'isbndb_isbn';
                    }
                }
                if ($candidates === []) {
                    cli()->info('Fetching data from ISBNdb for '.$bookInfo);
                    $candidates = array_merge($candidates, $isbnDb->searchBooks($bookInfo));
                    if ($candidates !== []) {
                        $resolvedSource = 'isbndb_search';
                    }
                }
            }

            if ($candidates === []) {
                if ($isbn !== null && $isbn !== '') {
                    cli()->info('Fetching data from Google Books by ISBN '.$isbn);
                    $candidate = $googleBooks->findByIsbn($isbn);
                    if (is_array($candidate)) {
                        $candidates[] = $candidate;
                        $resolvedSource = 'google_books_isbn';
                    }
                }
            }

            if ($candidates === []) {
                cli()->info('Fetching data from Google Books for '.$bookInfo);
                $candidates = array_merge(
                    $candidates,
                    $googleBooks->searchBooks(
                        $bookInfo,
                        $parsed->title,
                        $parsed->author,
                        $isbn
                    )
                );
                if ($candidates !== []) {
                    $resolvedSource = 'google_books_search';
                }
            }

            if ($candidates === []) {
                cli()->info('Fetching data from iTunes for '.$bookInfo);
                $candidates = array_merge($candidates, $this->fetchItunesBookProperties($bookInfo, $itunes));
                if ($candidates !== []) {
                    $resolvedSource = 'itunes_search';
                }
            }

            if ($candidates === []) {
                cli()->info('Fetching data from Open Library for '.$bookInfo);
                if ($isbn !== null && $isbn !== '') {
                    $candidate = $openLibrary->findByIsbn($isbn);
                    if (is_array($candidate)) {
                        $candidates[] = $candidate;
                        $resolvedSource = 'open_library_isbn';
                    }
                }

                if ($candidates === []) {
                    $candidates = array_merge($candidates, $openLibrary->searchBooks($bookInfo));
                    if ($candidates !== []) {
                        $resolvedSource = 'open_library_search';
                    }
                }
            }

            $book = $this->pickBestCandidate($candidates, $parsed, $scorer);
            if (is_array($book)) {
                Log::debug('Book metadata source resolved', [
                    'book_info' => $bookInfo,
                    'source' => $resolvedSource,
                    'isbn' => $isbn,
                ]);
            }
        }

        if (empty($book)) {
            return -2;
        }

        $check = BookInfo::query()->where('asin', $book['asin'])->first();
        if ($check === null && ! empty($book['isbn'])) {
            $check = BookInfo::query()->where('isbn', $book['isbn'])->first();
        }
        if ($check === null) {
            $bookId = BookInfo::query()->insertGetId(
                [
                    'title' => $book['title'],
                    'author' => $book['author'],
                    'asin' => $book['asin'],
                    'isbn' => $book['isbn'],
                    'ean' => $book['ean'],
                    'url' => $book['url'],
                    'salesrank' => $book['salesrank'],
                    'publisher' => $book['publisher'],
                    'publishdate' => $book['publishdate'],
                    'pages' => $book['pages'],
                    'overview' => $book['overview'],
                    'genre' => $book['genre'],
                    'cover' => $book['cover'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } else {
            $bookId = $check['id'];
            BookInfo::query()->where('id', $bookId)->update(
                [
                    'title' => $book['title'],
                    'author' => $book['author'],
                    'asin' => $book['asin'],
                    'isbn' => $book['isbn'],
                    'ean' => $book['ean'],
                    'url' => $book['url'],
                    'salesrank' => $book['salesrank'],
                    'publisher' => $book['publisher'],
                    'publishdate' => $book['publishdate'],
                    'pages' => $book['pages'],
                    'overview' => $book['overview'],
                    'genre' => $book['genre'],
                    'cover' => $book['cover'],
                ]
            );
        }

        if ($bookId && $bookId !== -2) {
            if ($this->echooutput) {
                cli()->header('Added/updated book: ');
                if ($book['author'] !== '') {
                    cli()->alternateOver('   Author: ').cli()->primary($book['author']);
                }
                cli()->alternateOver('   Title: ').cli()->primary(' '.$book['title']);
                if ($book['genre'] !== '') {
                    cli()->alternateOver('   Genre: ').cli()->primary(' '.$book['genre']);
                }
            }

            $book['cover'] = $ri->saveImage((string) $bookId, $book['coverurl'], $this->imgSavePath, 250, 250);
        } elseif ($this->echooutput) {
            cli()->header('Nothing to update: ').
            cli()->header($book['author'].
                ' - '.
                $book['title']);
        }

        return $bookId;
    }

    /**
     * Fetch book properties from iTunes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchItunesBookProperties(string $bookInfo, ?ItunesService $itunesService = null): array
    {
        $itunes = $itunesService ?? new ItunesService;
        $iTunesBooks = $itunes->findEbooks($bookInfo);

        if ($iTunesBooks === []) {
            cli()->notice('Could not find a match on iTunes!');

            return [];
        }

        $books = [];
        foreach ($iTunesBooks as $iTunesBook) {
            $books[] = [
                'title' => $iTunesBook['name'],
                'author' => $iTunesBook['author'],
                'asin' => (string) $iTunesBook['id'],
                'isbn' => null,
                'ean' => null,
                'url' => $iTunesBook['store_url'],
                'salesrank' => '',
                'publisher' => '',
                'pages' => '',
                'coverurl' => ! empty($iTunesBook['cover']) ? $iTunesBook['cover'] : '',
                'genre' => is_array($iTunesBook['genres']) ? implode(', ', $iTunesBook['genres']) : $iTunesBook['genre'],
                'overview' => strip_tags($iTunesBook['description'] ?? ''),
                'publishdate' => $iTunesBook['release_date'],
                'cover' => ! empty($iTunesBook['cover']) ? 1 : 0,
            ];
        }

        cli()->info('Found '.\count($books).' matching title(s) on iTunes');

        return $books;
    }

    /**
     * @param  array<int, BookInfo>  $books
     */
    private function pickBestExistingBook(array $books, ?BookParseResult $parsed): ?BookInfo
    {
        if ($books === []) {
            return null;
        }
        if ($parsed === null) {
            return $books[0];
        }

        $scorer = new BookMatchScorer;
        $best = null;
        $bestScore = 0.0;
        foreach ($books as $book) {
            $score = $scorer->scoreBookInfo($book, $parsed);
            if ($score > $bestScore) {
                $best = $book;
                $bestScore = $score;
            }
        }

        return $bestScore >= $this->minimumMatchScore($parsed) ? $best : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    private function pickBestCandidate(array $candidates, BookParseResult $parsed, BookMatchScorer $scorer): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $candidate) {
            $score = $scorer->score($candidate, $parsed);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best === null || $bestScore < $this->minimumMatchScore($parsed)) {
            return null;
        }

        return $best;
    }

    private function minimumMatchScore(?BookParseResult $parsed): float
    {
        if ($parsed === null) {
            return 0.55;
        }

        // No-author parses are more ambiguous and need a stricter cutoff.
        return $parsed->hasAuthor() ? 0.55 : 0.68;
    }
}

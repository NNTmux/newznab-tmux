<?php

namespace App\Services;

use App\Models\BookInfo;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service class for book data fetching and processing.
 */
class BookService
{
    public bool $echooutput;

    public ?string $pubkey;

    public ?string $privkey;

    public ?string $asstag;

    public int $bookqty;

    public int $sleeptime;

    public string $imgSavePath;

    public ?string $bookreqids;

    public string $renamed;

    /**
     * @var array<string, mixed>
     */
    public array $failCache;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echooutput = config('nntmux.echocli');

        $this->pubkey = Settings::settingValue('amazonpubkey');
        $this->privkey = Settings::settingValue('amazonprivkey');
        $this->asstag = Settings::settingValue('amazonassociatetag');
        $this->bookqty = Settings::settingValue('maxbooksprocessed') !== '' ? (int) Settings::settingValue('maxbooksprocessed') : 300;
        $this->sleeptime = Settings::settingValue('amazonsleep') !== '' ? (int) Settings::settingValue('amazonsleep') : 1000;
        $this->imgSavePath = storage_path('covers/book/');

        $this->bookreqids = (string) Category::BOOKS_EBOOK;
        $this->renamed = (int) Settings::settingValue('lookupbooks') === 2 ? 'AND isrenamed = 1' : '';

        $this->failCache = [];
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
    public function getBookInfoByName(string $title): ?Model
    {
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

        return BookInfo::search($searchWords)->first();
    }

    /**
     * Get book range with pagination.
     *
     * @param  array<string, mixed>  $cat
     * @param  array<string, mixed>  $excludedCats
     */
    public function getBookRange(int $page, array $cat, int $start, int $num, string $orderBy, array $excludedCats = []): mixed
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $browseby = $this->getBrowseBy();
        $catsrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) { // @phpstan-ignore offsetAccess.notFound
            $catsrch = Category::getCategorySearch($cat);
        }
        $exccatlist = '';
        if (\count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getBookOrder($orderBy);
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $showPasswords = app(\App\Services\Releases\ReleaseBrowseService::class)->showPasswords();

        $baseWhere = "boo.cover = 1 AND boo.title != '' "
            ."AND r.passwordstatus {$showPasswords} "
            .$browseby.' '
            .$catsrch.' '
            .$exccatlist;

        $cacheKey = md5('book_range_'.$baseWhere.$order[0].$order[1].$start.$num.$page); // @phpstan-ignore offsetAccess.notFound

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
        $bookSql = 'SELECT boo.id, boo.title, boo.author, boo.cover, boo.publisher, boo.publishdate, boo.review, boo.url, '
            .'MAX(r.postdate) AS latest_postdate, '
            .'COUNT(r.id) AS total_releases '
            .'FROM bookinfo boo '
            .'INNER JOIN releases r ON boo.id = r.bookinfo_id '
            .'WHERE '.$baseWhere.' '
            .'GROUP BY boo.id, boo.title, boo.author, boo.cover, boo.publisher, boo.publishdate, boo.review, boo.url '
            ."ORDER BY {$order[0]} {$order[1]} " // @phpstan-ignore offsetAccess.notFound
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
    public function getBrowseBy(): string
    {
        $browseby = ' ';
        foreach ($this->getBrowseByOptions() as $bbk => $bbv) {
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
        $bookids = [];
        if (ctype_digit((string) $this->bookreqids)) {
            $bookids[] = $this->bookreqids;
        } else {
            $bookids = explode(', ', $this->bookreqids);
        }

        $total = \count($bookids);
        if ($total > 0) {
            foreach ($bookids as $i => $iValue) {
                $query = Release::query()
                    ->whereNull('bookinfo_id')
                    ->whereIn('categories_id', [$iValue])
                    ->orderByDesc('postdate')
                    ->limit($this->bookqty);

                if ($guidChar !== '') {
                    $query->where('leftguid', 'like', $guidChar.'%');
                }

                if ($groupID !== '') {
                    $query->where('groups_id', $groupID);
                }

                $this->processBookReleasesHelper(
                    $query->get(['searchname', 'id', 'categories_id']), $iValue
                );
            }
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
                $usedAmazon = false;
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
                    $bookCheck = $this->getBookInfoByName($bookInfo);

                    if ($bookCheck === null && \in_array($bookInfo, $this->failCache, false)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echooutput) {
                            cli()->info('Cached previous failure. Skipping.');
                        }
                        $bookId = -2;
                    } elseif ($bookCheck === null) {
                        $bookId = $this->updateBookInfo($bookInfo);
                        $usedAmazon = true;
                        if ($bookId === -2) {
                            $this->failCache[] = $bookInfo;
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
                // Sleep to not flood amazon.
                $diff = floor((now()->timestamp - $startTime) * 1000000);
                if ($this->sleeptime * 1000 - $diff > 0 && $usedAmazon === true) {
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
        $a = preg_replace('/\d{1,2} \d{1,2} \d{2,4}|(19|20)\d\d|anybody got .+?[a-z]\? |[ ._-](Novel|TIA)([ ._-]|$)|([ \.])HQ([-\. ])|[\(\)\.\-_ ](AVI|AZW3?|DOC|EPUB|LIT|MOBI|NFO|RETAIL|(si)?PDF|RTF|TXT)[\)\]\.\-_ ](?![a-z0-9])|compleet|DAGSTiDNiNGEN|DiRFiX|\+ extra|r?e ?Books?([\.\-_ ]English|ers)?|azw3?|ePu([bp])s?|html|mobi|^NEW[\.\-_ ]|PDF([\.\-_ ]English)?|Please post more|Post description|Proper|Repack(fix)?|[\.\-_ ](Chinese|English|French|German|Italian|Retail|Scan|Swedish)|^R4 |Repost|Skytwohigh|TIA!+|TruePDF|V413HAV|(would someone )?please (re)?post.+? "|with the authors name right/i', '', $release_name);
        $b = preg_replace('/^(As Req |conversion |eq |Das neue Abenteuer \d+|Fixed version( ignore previous post)?|Full |Per Req As Found|(\s+)?R4 |REQ |revised |version |\d+(\s+)?$)|(COMPLETE|INTERNAL|RELOADED| (AZW3|eB|docx|ENG?|exe|FR|Fix|gnv64|MU|NIV|R\d\s+\d{1,2} \d{1,2}|R\d|Req|TTL|UC|v(\s+)?\d))(\s+)?$/i', '', $a);

        // remove book series from title as this gets more matches on amazon
        $c = preg_replace('/ - \[.+\]|\[.+\]/', '', $b);

        // remove any brackets left behind
        $d = preg_replace('/(\(\)|\[\])/', '', $c);
        $releasename = trim(preg_replace('/\s\s+/i', ' ', $d));

        // the default existing type was ebook, this handles that in the same manor as before
        if ($releasetype === 'ebook') {
            if (preg_match('/^([a-z0-9] )+$|ArtofUsenet|ekiosk|(ebook|mobi).+collection|erotica|Full Video|ImwithJamie|linkoff org|Mega.+pack|^[a-z0-9]+ (?!((January|February|March|April|May|June|July|August|September|O([ck])tober|November|De([cz])ember)))[a-z]+( (ebooks?|The))?$|NY Times|(Book|Massive) Dump|Sexual/i', $releasename)) {
                if ($this->echooutput) {
                    cli()->headerOver('Changing category to misc books: ').cli()->primary($releasename);
                }
                Release::query()->where('id', $releaseID)->update(['categories_id' => Category::BOOKS_UNKNOWN]);

                return false;
            }

            if (preg_match('/^([a-z0-9ü!]+ ){1,2}(N|Vol)?\d{1,4}([abc])?$|^([a-z0-9]+ ){1,2}(Jan( |unar|$)|Feb( |ruary|$)|Mar( |ch|$)|Apr( |il|$)|May(?![a-z0-9])|Jun([ e$])|Jul([ y$])|Aug( |ust|$)|Sep( |tember|$)|O([ck])t( |ober|$)|Nov( |ember|$)|De([cz])( |ember|$))/ui', $releasename) && ! preg_match('/Part \d+/i', $releasename)) {
                if ($this->echooutput) {
                    cli()->headerOver('Changing category to magazines: ').cli()->primary($releasename);
                }
                Release::query()->where('id', $releaseID)->update(['categories_id' => Category::BOOKS_MAGAZINES]);

                return false;
            }
            if (! empty($releasename) && ! preg_match('/^[a-z0-9]+$|^([0-9]+ ){1,}$|Part \d+/i', $releasename)) {
                return $releasename;
            }

            return false;
        }
        if ($releasetype === 'audiobook') {
            if (! empty($releasename) && ! preg_match('/^[a-z0-9]+$|^([0-9]+ ){1,}$|Part \d+/i', $releasename)) {
                // we can skip category for audiobooks, since we already know it, so as long as the release name is valid return it so that it is postprocessed by amazon.  In the future, determining the type of audiobook could be added (Lecture or book), since we can skip lookups on lectures, but for now handle them all the same way
                return $releasename;
            }

            return false;
        }

        return false;
    }

    /**
     * Update book info from external sources.
     *
     * @return false|int|string
     *
     * @throws \Exception
     */
    public function updateBookInfo(string $bookInfo = '', mixed $amazdata = null)
    {
        $ri = new ReleaseImageService;

        $bookId = -2;

        $book = false;
        if ($bookInfo !== '') {
            cli()->info('Fetching data from iTunes for '.$bookInfo);
            $book = $this->fetchItunesBookProperties($bookInfo);
        }

        if (empty($book)) {
            return false;
        }

        $check = BookInfo::query()->where('asin', $book['asin'])->first();
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
                if ($book['genre'] !== 'null') {
                    cli()->alternateOver('   Genre: ').cli()->primary(' '.$book['genre']);
                }
            }

            $book['cover'] = $ri->saveImage($bookId, $book['coverurl'], $this->imgSavePath, 250, 250);
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
     * @return array<string, mixed>|bool
     */
    public function fetchItunesBookProperties(string $bookInfo)
    {
        $itunes = new ItunesService;
        $iTunesBook = $itunes->findEbook($bookInfo);

        if ($iTunesBook === null) {
            cli()->notice('Could not find a match on iTunes!');

            return false;
        }

        cli()->info('Found matching title: '.$iTunesBook['name']);

        $book = [
            'title' => $iTunesBook['name'],
            'author' => $iTunesBook['author'],
            'asin' => $iTunesBook['id'],
            'isbn' => 'null',
            'ean' => 'null',
            'url' => $iTunesBook['store_url'],
            'salesrank' => '',
            'publisher' => '',
            'pages' => '',
            'coverurl' => ! empty($iTunesBook['cover']) ? $iTunesBook['cover'] : '',
            'genre' => is_array($iTunesBook['genres']) ? implode(', ', $iTunesBook['genres']) : $iTunesBook['genre'],
            'overview' => strip_tags($iTunesBook['description'] ?? ''),
            'publishdate' => $iTunesBook['release_date'],
        ];

        if (! empty($book['coverurl'])) {
            $book['cover'] = 1;
        } else {
            $book['cover'] = 0;
        }

        return $book;
    }
}

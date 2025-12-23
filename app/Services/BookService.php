<?php

namespace App\Services;

use App\Models\BookInfo;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\ReleaseImage;
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

    public array $failCache;

    protected ColorCLI $colorCli;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echooutput = config('nntmux.echocli');
        $this->colorCli = new ColorCLI;

        $this->pubkey = Settings::settingValue('amazonpubkey');
        $this->privkey = Settings::settingValue('amazonprivkey');
        $this->asstag = Settings::settingValue('amazonassociatetag');
        $this->bookqty = Settings::settingValue('maxbooksprocessed') !== '' ? (int) Settings::settingValue('maxbooksprocessed') : 300;
        $this->sleeptime = Settings::settingValue('amazonsleep') !== '' ? (int) Settings::settingValue('amazonsleep') : 1000;
        $this->imgSavePath = storage_path('covers/book/');

        $this->bookreqids = Category::BOOKS_EBOOK;
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
     */
    public function getBookRange(int $page, array $cat, int $start, int $num, string $orderBy, array $excludedCats = []): array
    {
        $page = max(1, $page);
        $start = max(0, $start);

        $browseby = $this->getBrowseBy();
        $catsrch = '';
        if (\count($cat) > 0 && $cat[0] !== -1) {
            $catsrch = Category::getCategorySearch($cat);
        }
        $exccatlist = '';
        if (\count($excludedCats) > 0) {
            $exccatlist = ' AND r.categories_id NOT IN ('.implode(',', $excludedCats).')';
        }
        $order = $this->getBookOrder($orderBy);
        $booksql = sprintf(
            "
				SELECT SQL_CALC_FOUND_ROWS boo.id,
					GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id
				FROM bookinfo boo
				LEFT JOIN releases r ON boo.id = r.bookinfo_id
				WHERE boo.cover = 1
				AND boo.title != ''
				AND r.passwordstatus %s
				%s %s %s
				GROUP BY boo.id
				ORDER BY %s %s %s",
            app(\App\Services\Releases\ReleaseBrowseService::class)->showPasswords(),
            $browseby,
            $catsrch,
            $exccatlist,
            $order[0],
            $order[1],
            ($start === false ? '' : ' LIMIT '.$num.' OFFSET '.$start)
        );
        $expiresAt = now()->addMinutes(config('nntmux.cache_expiry_medium'));
        $booksCache = Cache::get(md5($booksql.$page));
        if ($booksCache !== null) {
            $books = $booksCache;
        } else {
            $data = DB::select($booksql);
            $books = ['total' => DB::select('SELECT FOUND_ROWS() AS total'), 'result' => $data];
            Cache::put(md5($booksql.$page), $books, $expiresAt);
        }
        $bookIDs = $releaseIDs = [];
        if (\is_array($books['result'])) {
            foreach ($books['result'] as $book => $id) {
                $bookIDs[] = $id->id;
                $releaseIDs[] = $id->grp_release_id;
            }
        }
        $sql = sprintf(
            '
			SELECT
				r.id, r.rarinnerfilecount, r.grabs, r.comments, r.totalpart, r.size, r.postdate, r.searchname, r.haspreview, r.passwordstatus, r.guid, df.failed AS failed,
			boo.*,
			r.bookinfo_id,
			g.name AS group_name,
			rn.releases_id AS nfoid
			FROM releases r
			LEFT OUTER JOIN usenet_groups g ON g.id = r.groups_id
			LEFT OUTER JOIN release_nfos rn ON rn.releases_id = r.id
			LEFT OUTER JOIN dnzb_failures df ON df.release_id = r.id
			INNER JOIN bookinfo boo ON boo.id = r.bookinfo_id
			WHERE boo.id IN (%s)
			AND r.id IN (%s)
			%s
			GROUP BY boo.id
			ORDER BY %s %s',
            \is_array($bookIDs) && ! empty($bookIDs) ? implode(',', $bookIDs) : -1,
            \is_array($releaseIDs) && ! empty($releaseIDs) ? implode(',', $releaseIDs) : -1,
            $catsrch,
            $order[0],
            $order[1]
        );
        $return = Cache::get(md5($sql.$page));
        if ($return !== null) {
            return $return;
        }
        $return = DB::select($sql);
        if (\count($return) > 0) {
            $return[0]->_totalcount = $books['total'][0]->total ?? 0;
        }
        Cache::put(md5($sql.$page), $return, $expiresAt);

        return $return;
    }

    /**
     * Get book order array.
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
        $publishdate,
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
    protected function processBookReleasesHelper($res, $categoryID): void
    {
        if ($res->count() > 0) {
            if ($this->echooutput) {
                $this->colorCli->header('Processing '.$res->count().' book release(s) for categories id '.$categoryID);
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
                        $this->colorCli->info('Looking up: '.$bookInfo);
                    }

                    // Do a local lookup first
                    $bookCheck = $this->getBookInfoByName($bookInfo);

                    if ($bookCheck === null && \in_array($bookInfo, $this->failCache, false)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echooutput) {
                            $this->colorCli->info('Cached previous failure. Skipping.');
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
                    usleep($this->sleeptime * 1000 - $diff);
                }
            }
        } elseif ($this->echooutput) {
            $this->colorCli->header('No book releases to process for categories id '.$categoryID);
        }
    }

    /**
     * Parse release title.
     *
     * @return bool|string
     */
    public function parseTitle($release_name, $releaseID, $releasetype)
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
                    $this->colorCli->headerOver('Changing category to misc books: ').$this->colorCli->primary($releasename);
                }
                Release::query()->where('id', $releaseID)->update(['categories_id' => Category::BOOKS_UNKNOWN]);

                return false;
            }

            if (preg_match('/^([a-z0-9ü!]+ ){1,2}(N|Vol)?\d{1,4}([abc])?$|^([a-z0-9]+ ){1,2}(Jan( |unar|$)|Feb( |ruary|$)|Mar( |ch|$)|Apr( |il|$)|May(?![a-z0-9])|Jun([ e$])|Jul([ y$])|Aug( |ust|$)|Sep( |tember|$)|O([ck])t( |ober|$)|Nov( |ember|$)|De([cz])( |ember|$))/ui', $releasename) && ! preg_match('/Part \d+/i', $releasename)) {
                if ($this->echooutput) {
                    $this->colorCli->headerOver('Changing category to magazines: ').$this->colorCli->primary($releasename);
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
    public function updateBookInfo(string $bookInfo = '', $amazdata = null)
    {
        $ri = new ReleaseImage;

        $bookId = -2;

        $book = false;
        if ($bookInfo !== '') {
            if (! $book) {
                $this->colorCli->info('Fetching data from iTunes for '.$bookInfo);
                $book = $this->fetchItunesBookProperties($bookInfo);
            } elseif ($amazdata !== null) {
                $book = $amazdata;
            }
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
            if ($check !== null) {
                $bookId = $check['id'];
            }
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
                $this->colorCli->header('Added/updated book: ');
                if ($book['author'] !== '') {
                    $this->colorCli->alternateOver('   Author: ').$this->colorCli->primary($book['author']);
                }
                $this->colorCli->alternateOver('   Title: ').$this->colorCli->primary(' '.$book['title']);
                if ($book['genre'] !== 'null') {
                    $this->colorCli->alternateOver('   Genre: ').$this->colorCli->primary(' '.$book['genre']);
                }
            }

            $book['cover'] = $ri->saveImage($bookId, $book['coverurl'], $this->imgSavePath, 250, 250);
        } elseif ($this->echooutput) {
            $this->colorCli->header('Nothing to update: ').
            $this->colorCli->header($book['author'].
                ' - '.
                $book['title']);
        }

        return $bookId;
    }

    /**
     * Fetch book properties from iTunes.
     *
     * @return array|bool
     */
    public function fetchItunesBookProperties(string $bookInfo)
    {
        $itunes = new ItunesService();
        $iTunesBook = $itunes->findEbook($bookInfo);

        if ($iTunesBook === null) {
            $this->colorCli->notice('Could not find a match on iTunes!');

            return false;
        }

        $this->colorCli->info('Found matching title: '.$iTunesBook['name']);

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


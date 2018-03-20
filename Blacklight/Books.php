<?php

namespace Blacklight;

use ApaiIO\ApaiIO;
use Blacklight\db\DB;
use GuzzleHttp\Client;
use App\Models\Release;
use App\Models\BookInfo;
use App\Models\Category;
use App\Models\Settings;
use ApaiIO\Operations\Search;
use Illuminate\Support\Carbon;
use ApaiIO\Configuration\Country;
use ApaiIO\Request\GuzzleRequest;
use Illuminate\Support\Facades\Cache;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\ResponseTransformer\XmlToSimpleXmlObject;

class Books
{
    /**
     * @var \Blacklight\db\DB
     */
    public $pdo;

    /**
     * @var bool
     */
    public $echooutput;

    /**
     * @var null|string
     */
    public $pubkey;

    /**
     * @var null|string
     */
    public $privkey;

    /**
     * @var null|string
     */
    public $asstag;

    /**
     * @var int|null|string
     */
    public $bookqty;

    /**
     * @var int|null|string
     */
    public $sleeptime;

    /**
     * @var string
     */
    public $imgSavePath;

    /**
     * @var null|string
     */
    public $bookreqids;

    /**
     * @var string
     */
    public $renamed;

    /**
     * @var array
     */
    public $failCache;

    /**
     * @param array $options Class instances / Echo to cli.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo'     => false,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->echooutput = ($options['Echo'] && config('nntmux.echocli'));
        $this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());

        $this->pubkey = Settings::settingValue('APIs..amazonpubkey');
        $this->privkey = Settings::settingValue('APIs..amazonprivkey');
        $this->asstag = Settings::settingValue('APIs..amazonassociatetag');
        $this->bookqty = Settings::settingValue('..maxbooksprocessed') !== '' ? (int) Settings::settingValue('..maxbooksprocessed') : 300;
        $this->sleeptime = Settings::settingValue('..amazonsleep') !== '' ? (int) Settings::settingValue('..amazonsleep') : 1000;
        $this->imgSavePath = NN_COVERS.'book'.DS;
        $result = Settings::settingValue('..book_reqids');
        $this->bookreqids = $result ?? Category::BOOKS_EBOOK;
        $this->renamed = (int) Settings::settingValue('..lookupbooks') === 2 ? 'AND isrenamed = 1' : '';

        $this->failCache = [];
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getBookInfo($id)
    {
        return BookInfo::query()->where('id', $id)->first();
    }

    /**
     * @param $title
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getBookInfoByName($title)
    {

        //only used to get a count of words
        $searchWords = $searchsql = '';
        $title = preg_replace('/( - | -|\(.+\)|\(|\))/', ' ', $title);
        $title = preg_replace('/[^\w ]+/', '', $title);
        $title = trim(preg_replace('/\s\s+/i', ' ', $title));
        $title = trim($title);
        $words = explode(' ', $title);

        foreach ($words as $word) {
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
     * @param $cat
     * @param $orderby
     * @param array $excludedcats
     * @return array
     * @throws \Exception
     */
    public function getBookRange($cat, $orderby, array $excludedcats = []): array
    {
        $catsrch = '';

        $order = $this->getBookOrder($orderby);

        $booksql = BookInfo::query()
            ->where('releases.nzbstatus', '=', 1)
            ->where('bookinfo.cover', '=', 1)
            ->where('bookinfo.title', '!=', '')
            ->selectRaw("GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id");
        Releases::showPasswords($booksql, true);
        if (\count($cat) > 0 && $cat[0] !== -1) {
            Category::getCategorySearch($cat, $booksql, true);
        }

        if (\count($excludedcats) > 0) {
            $booksql->whereNotIn('releases.categories_id', $excludedcats);
        }

        $booksql->groupBy('bookinfo.id')
            ->orderBy($order[0], $order[1]);

        $expiresAt = Carbon::now()->addSeconds(config('nntmux.cache_expiry_medium'));
        $bookscache = Cache::get(md5($cat.$orderby.implode('.', $excludedcats)));
        if ($bookscache !== null) {
            $books = $bookscache;
        } else {
            $books = $booksql->paginate(config('nntmux.items_per_page'));
            Cache::put(md5($cat.$orderby.implode('.', $excludedcats)), $books, $expiresAt);
        }

        $bookIDs = $releaseIDs = false;

        if (\is_array($books->items())) {
            foreach ($books->items() as $book => $id) {
                $bookIDs[] = $id['id'];
                $releaseIDs[] = $id['grp_release_id'];
            }
        }

        $sql = Release::query()
            ->selectRaw("GROUP_CONCAT(r.id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_id,
				GROUP_CONCAT(r.rarinnerfilecount ORDER BY r.postdate DESC SEPARATOR ',') as grp_rarinnerfilecount,
				GROUP_CONCAT(r.haspreview ORDER BY r.postdate DESC SEPARATOR ',') AS grp_haspreview,
				GROUP_CONCAT(r.passwordstatus ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_password,
				GROUP_CONCAT(r.guid ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_guid,
				GROUP_CONCAT(rn.releases_id ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_nfoid,
				GROUP_CONCAT(g.name ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_grpname,
				GROUP_CONCAT(r.searchname ORDER BY r.postdate DESC SEPARATOR '#') AS grp_release_name,
				GROUP_CONCAT(r.postdate ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_postdate,
				GROUP_CONCAT(r.size ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_size,
				GROUP_CONCAT(r.totalpart ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_totalparts,
				GROUP_CONCAT(r.comments ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_comments,
				GROUP_CONCAT(r.grabs ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_grabs,
				GROUP_CONCAT(df.failed ORDER BY r.postdate DESC SEPARATOR ',') AS grp_release_failed")
            ->select(['boo.*', 'g.name as group_name', 'rn.releases_id as nfoid'])
            ->leftJoin('groups as g', 'g.id', '=', 'releases.groups_id')
            ->leftJoin('release_nfos as rn', 'rn.releases_id', '=', 'releases.id')
            ->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'releases.id')
            ->join('bookinfo as boo', 'boo.id', '=', 'releases.bookinfo_id')
            ->whereIn('boo.id', \is_array($bookIDs) ? implode(',', $bookIDs) : -1)
            ->whereIn('releases.id', \is_array($releaseIDs) ? implode(',', $releaseIDs) : -1)
            ->groupBy('boo.id')
            ->orderBy($order[0], $order[1]);

        $return = Cache::get(md5($cat.$orderby.implode('.', $excludedcats)));
        if ($return !== null) {
            return $return;
        }
        $return = $sql->get();
        if ($return !== null) {
            $return[0]['_totalcount'] = $books->total();
        }
        Cache::put(md5($cat.$orderby.implode('.', $excludedcats), $return, $expiresAt));

        return $return;
    }

    /**
     * @param $orderby
     *
     * @return array
     */
    public function getBookOrder($orderby): array
    {
        $order = ($orderby === '') ? 'r.postdate' : $orderby;
        $orderArr = explode('_', $order);
        switch ($orderArr[0]) {
            case 'title':
                $orderfield = 'boo.title';
                break;
            case 'author':
                $orderfield = 'boo.author';
                break;
            case 'publishdate':
                $orderfield = 'boo.publishdate';
                break;
            case 'size':
                $orderfield = 'r.size';
                break;
            case 'files':
                $orderfield = 'r.totalpart';
                break;
            case 'stats':
                $orderfield = 'r.grabs';
                break;
            case 'posted':
            default:
                $orderfield = 'r.postdate';
                break;
        }
        $ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';

        return [$orderfield, $ordersort];
    }

    /**
     * @return array
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
     * @return array
     */
    public function getBrowseByOptions(): array
    {
        return ['author' => 'author', 'title' => 'title'];
    }

    /**
     * @return string
     */
    public function getBrowseBy(): string
    {
        $browseby = ' ';
        $browsebyArr = $this->getBrowseByOptions();
        foreach ($browsebyArr as $bbk => $bbv) {
            if (request()->has($bbk)) {
                $bbs = stripslashes(request()->input($bbk));
                $browseby .= 'AND boo.'.$bbv.' '.$this->pdo->likeString($bbs, true, true);
            }
        }

        return $browseby;
    }

    /**
     * @param $title
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function fetchAmazonProperties($title)
    {
        $conf = new GenericConfiguration();
        $client = new Client();
        $request = new GuzzleRequest($client);

        try {
            $conf
                ->setCountry(Country::INTERNATIONAL)
                ->setAccessKey($this->pubkey)
                ->setSecretKey($this->privkey)
                ->setAssociateTag($this->asstag)
                ->setRequest($request)
                ->setResponseTransformer(new XmlToSimpleXmlObject());
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

        $search = new Search();
        $search->setCategory('Books');
        $search->setKeywords($title);
        $search->setResponseGroup(['Large']);

        $apaiIo = new ApaiIO($conf);

        $response = $apaiIo->runOperation($search);
        if ($response === false) {
            throw new \RuntimeException('Could not connect to Amazon');
        }

        if (isset($response->Items->Item->ItemAttributes->Title)) {
            ColorCLI::doEcho(ColorCLI::info('Found matching title: '.$response->Items->Item->ItemAttributes->Title), true);

            return $response;
        }

        ColorCLI::doEcho(ColorCLI::notice('Could not find a match on Amazon!'), true);

        return false;
    }

    /**
     * Process book releases, 1 category at a time.
     *
     * @throws \Exception
     */
    public function processBookReleases(): void
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
                $this->processBookReleasesHelper(
                    Release::query()->where('nzbstatus', '=', NZB::NZB_ADDED)
                        ->whereNull('bookinfo_id')
                        ->whereIn('categories_id', [$bookids[$i]])
                    ->orderBy('postdate', 'desc')
                    ->limit($this->bookqty)
                    ->get(['searchname', 'id', 'categories_id']),
                    $bookids[$i]
                );
            }
        }
    }

    /**
     * @param $res
     * @param $categoryID
     *
     * @throws \Exception
     */
    protected function processBookReleasesHelper($res, $categoryID): void
    {
        if ($res->count() > 0) {
            if ($this->echooutput) {
                ColorCLI::doEcho(ColorCLI::header("\nProcessing ".$res->count().' book release(s) for categories id '.$categoryID), true);
            }

            $bookId = -2;
            foreach ($res as $arr) {
                $startTime = microtime(true);
                $usedAmazon = false;
                // audiobooks are also books and should be handled in an identical manor, even though it falls under a music category
                if ($arr['categories_id'] === '3030') {
                    // audiobook
                    $bookInfo = $this->parseTitle($arr['searchname'], $arr['id'], 'audiobook');
                } else {
                    // ebook
                    $bookInfo = $this->parseTitle($arr['searchname'], $arr['id'], 'ebook');
                }

                if ($bookInfo !== false) {
                    if ($this->echooutput) {
                        ColorCLI::doEcho(ColorCLI::headerOver('Looking up: ').ColorCLI::primary($bookInfo), true);
                    }

                    // Do a local lookup first
                    $bookCheck = $this->getBookInfoByName($bookInfo);

                    if ($bookCheck === null && \in_array($bookInfo, $this->failCache, false)) {
                        // Lookup recently failed, no point trying again
                        if ($this->echooutput) {
                            ColorCLI::doEcho(ColorCLI::headerOver('Cached previous failure. Skipping.'), true);
                        }
                        $bookId = -2;
                    } elseif ($bookCheck === null) {
                        $bookId = $this->updateBookInfo($bookInfo);
                        $usedAmazon = true;
                        if ($bookId === -2) {
                            $this->failCache[] = $bookInfo;
                        }
                    } else {
                        if ($bookCheck !== null) {
                            $bookId = $bookCheck['id'];
                        }
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
                $diff = floor((microtime(true) - $startTime) * 1000000);
                if ($this->sleeptime * 1000 - $diff > 0 && $usedAmazon === true) {
                    usleep($this->sleeptime * 1000 - $diff);
                }
            }
        } elseif ($this->echooutput) {
            ColorCLI::doEcho(ColorCLI::header('No book releases to process for categories id '.$categoryID), true);
        }
    }

    /**
     * @param $release_name
     * @param $releaseID
     * @param $releasetype
     *
     * @return bool|string
     */
    public function parseTitle($release_name, $releaseID, $releasetype)
    {
        $a = preg_replace('/\d{1,2} \d{1,2} \d{2,4}|(19|20)\d\d|anybody got .+?[a-z]\? |[-._ ](Novel|TIA)([-._ ]|$)|( |\.)HQ(-|\.| )|[\(\)\.\-_ ](AVI|AZW3?|DOC|EPUB|LIT|MOBI|NFO|RETAIL|(si)?PDF|RTF|TXT)[\)\]\.\-_ ](?![a-z0-9])|compleet|DAGSTiDNiNGEN|DiRFiX|\+ extra|r?e ?Books?([\.\-_ ]English|ers)?|azw3?|ePu(b|p)s?|html|mobi|^NEW[\.\-_ ]|PDF([\.\-_ ]English)?|Please post more|Post description|Proper|Repack(fix)?|[\.\-_ ](Chinese|English|French|German|Italian|Retail|Scan|Swedish)|^R4 |Repost|Skytwohigh|TIA!+|TruePDF|V413HAV|(would someone )?please (re)?post.+? "|with the authors name right/i', '', $release_name);
        $b = preg_replace('/^(As Req |conversion |eq |Das neue Abenteuer \d+|Fixed version( ignore previous post)?|Full |Per Req As Found|(\s+)?R4 |REQ |revised |version |\d+(\s+)?$)|(COMPLETE|INTERNAL|RELOADED| (AZW3|eB|docx|ENG?|exe|FR|Fix|gnv64|MU|NIV|R\d\s+\d{1,2} \d{1,2}|R\d|Req|TTL|UC|v(\s+)?\d))(\s+)?$/i', '', $a);

        //remove book series from title as this gets more matches on amazon
        $c = preg_replace('/ - \[.+\]|\[.+\]/', '', $b);

        //remove any brackets left behind
        $d = preg_replace('/(\(\)|\[\])/', '', $c);
        $releasename = trim(preg_replace('/\s\s+/i', ' ', $d));

        // the default existing type was ebook, this handles that in the same manor as before
        if ($releasetype === 'ebook') {
            if (preg_match('/^([a-z0-9] )+$|ArtofUsenet|ekiosk|(ebook|mobi).+collection|erotica|Full Video|ImwithJamie|linkoff org|Mega.+pack|^[a-z0-9]+ (?!((January|February|March|April|May|June|July|August|September|O(c|k)tober|November|De(c|z)ember)))[a-z]+( (ebooks?|The))?$|NY Times|(Book|Massive) Dump|Sexual/i', $releasename)) {
                if ($this->echooutput) {
                    ColorCLI::doEcho(
                        ColorCLI::headerOver('Changing category to misc books: ').ColorCLI::primary($releasename), true
                    );
                }
                Release::query()->where('id', $releaseID)->update(['categories_id' => Category::BOOKS_UNKNOWN]);

                return false;
            }

            if (preg_match('/^([a-z0-9ü!]+ ){1,2}(N|Vol)?\d{1,4}(a|b|c)?$|^([a-z0-9]+ ){1,2}(Jan( |unar|$)|Feb( |ruary|$)|Mar( |ch|$)|Apr( |il|$)|May(?![a-z0-9])|Jun( |e|$)|Jul( |y|$)|Aug( |ust|$)|Sep( |tember|$)|O(c|k)t( |ober|$)|Nov( |ember|$)|De(c|z)( |ember|$))/ui', $releasename) && ! preg_match('/Part \d+/i', $releasename)) {
                if ($this->echooutput) {
                    ColorCLI::doEcho(
                        ColorCLI::headerOver('Changing category to magazines: ').ColorCLI::primary($releasename), true
                    );
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
     * @param string $bookInfo
     * @param null   $amazdata
     *
     * @return false|int|string
     * @throws \Exception
     */
    public function updateBookInfo($bookInfo = '', $amazdata = null)
    {
        $ri = new ReleaseImage();

        $book = [];
        $bookId = -2;

        $amaz = false;
        if ($bookInfo !== '') {
            ColorCLI::doEcho(ColorCLI::info('Fetching data from Amazon for '.$bookInfo), true);

            $amaz = $this->fetchAmazonProperties($bookInfo);
        } elseif ($amazdata !== null) {
            $amaz = $amazdata;
        }

        if (! $amaz) {
            return false;
        }

        $book['title'] = (string) $amaz->Items->Item->ItemAttributes->Title;
        $book['author'] = (string) $amaz->Items->Item->ItemAttributes->Author;
        $book['asin'] = (string) $amaz->Items->Item->ASIN;
        $book['isbn'] = (string) $amaz->Items->Item->ItemAttributes->ISBN;
        if ($book['isbn'] === '') {
            $book['isbn'] = 'null';
        }

        $book['ean'] = (string) $amaz->Items->Item->ItemAttributes->EAN;
        if ($book['ean'] === '') {
            $book['ean'] = 'null';
        }

        $book['url'] = (string) $amaz->Items->Item->DetailPageURL;
        $book['url'] = str_replace('%26tag%3Dws', '%26tag%3Dopensourceins%2D21', $book['url']);

        $book['salesrank'] = (string) $amaz->Items->Item->SalesRank;
        if ($book['salesrank'] === '') {
            $book['salesrank'] = 'null';
        }

        $book['publisher'] = (string) $amaz->Items->Item->ItemAttributes->Publisher;
        if ($book['publisher'] === '') {
            $book['publisher'] = 'null';
        }

        $book['publishdate'] = date('Y-m-d', strtotime((string) $amaz->Items->Item->ItemAttributes->PublicationDate));
        if ($book['publishdate'] === '') {
            $book['publishdate'] = 'null';
        }

        $book['pages'] = (string) $amaz->Items->Item->ItemAttributes->NumberOfPages;
        if ($book['pages'] === '') {
            $book['pages'] = 'null';
        }

        if (isset($amaz->Items->Item->EditorialReviews->EditorialReview->Content)) {
            $book['overview'] = strip_tags((string) $amaz->Items->Item->EditorialReviews->EditorialReview->Content);
            if ($book['overview'] === '') {
                $book['overview'] = 'null';
            }
        } else {
            $book['overview'] = 'null';
        }

        if (isset($amaz->Items->Item->BrowseNodes->BrowseNode->Name)) {
            $book['genre'] = (string) $amaz->Items->Item->BrowseNodes->BrowseNode->Name;
            if ($book['genre'] === '') {
                $book['genre'] = 'null';
            }
        } else {
            $book['genre'] = 'null';
        }

        $book['coverurl'] = (string) $amaz->Items->Item->LargeImage->URL;
        if ($book['coverurl'] !== '') {
            $book['cover'] = 1;
        } else {
            $book['cover'] = 0;
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
                    'overview' =>$book['overview'],
                    'genre' => $book['genre'],
                    'cover' => $book['cover'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
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
                ColorCLI::doEcho(ColorCLI::header('Added/updated book: '), true);
                if ($book['author'] !== '') {
                    ColorCLI::doEcho(ColorCLI::alternateOver('   Author: ').ColorCLI::primary($book['author']), true);
                }
                echo ColorCLI::alternateOver('   Title: ').ColorCLI::primary(' '.$book['title']);
                if ($book['genre'] !== 'null') {
                    ColorCLI::doEcho(ColorCLI::alternateOver('   Genre: ').ColorCLI::primary(' '.$book['genre']), true);
                }
            }

            $book['cover'] = $ri->saveImage($bookId, $book['coverurl'], $this->imgSavePath, 250, 250);
        } else {
            if ($this->echooutput) {
                ColorCLI::doEcho(
                    ColorCLI::header('Nothing to update: ').
                    ColorCLI::header($book['author'].
                        ' - '.
                        $book['title']), true
                );
            }
        }

        return $bookId;
    }
}

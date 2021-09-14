<?php

namespace Blacklight\db\populate;

use App\Models\AnidbEpisode;
use App\Models\AnidbInfo;
use App\Models\AnidbTitle;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\ReleaseImage;
use Illuminate\Support\Carbon;

class AniDB
{
    private const CLIENT_VERSION = 2;

    /**
     * Whether or not to echo message output.
     *
     * @var bool
     */
    public $echooutput;

    /**
     * The directory to store AniDB covers.
     *
     * @var string
     */
    public $imgSavePath;

    /**
     * The name of the nZEDb client for AniDB lookups.
     *
     * @var string
     */
    private $apiKey;

    /**
     * Whether or not AniDB thinks our client is banned.
     *
     * @var bool
     */
    private $banned;

    /**
     * The last unixtime a full AniDB update was run.
     *
     * @var string
     */
    private $lastUpdate;

    /**
     * The number of days between full AniDB updates.
     *
     * @var string
     */
    private $updateInterval;

    /**
     * @var \Blacklight\ColorCLI
     */
    protected $colorCli;

    /**
     * @param  array  $options  Class instances / Echo to cli.
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
        $this->colorCli = new ColorCLI();

        $anidbupdint = Settings::settingValue('APIs.AniDB.max_update_frequency');
        $lastupdated = Settings::settingValue('APIs.AniDB.last_full_update');

        $this->imgSavePath = NN_COVERS.'anime'.DS;
        $this->apiKey = config('nntmux_api.anidb_api_key');

        $this->updateInterval = $anidbupdint ?? '7';
        $this->lastUpdate = $lastupdated ?? '0';
        $this->banned = false;
    }

    /**
     * Main switch that initiates AniDB table population.
     *
     * @param  string  $type
     * @param  int|string  $anidbId
     *
     * @throws \Exception
     */
    public function populateTable($type = '', $anidbId = ''): void
    {
        switch ($type) {
            case 'full':
                $this->populateMainTable();
                break;
            case 'info':
                $this->populateInfoTable($anidbId);
                break;
        }
    }

    /**
     * @param $anidbId
     * @return array|false
     */
    private function getAniDbAPI($anidbId)
    {
        $timestamp = Settings::settingValue('APIs.AniDB.banned') + 90000;
        if ($timestamp > time()) {
            echo 'Banned from AniDB lookups until '.date('Y-m-d H:i:s', $timestamp).PHP_EOL;

            return false;
        }
        $apiresponse = $this->getAniDbResponse($anidbId);

        $AniDBAPIArray = [];

        if ($apiresponse === false) {
            echo 'AniDB: Error getting response.'.PHP_EOL;
        } elseif (preg_match('/\<error\>Banned\<\/error\>/', $apiresponse)) {
            $this->banned = true;
            Settings::query()->where(['section' => 'APIs', 'subsection' => 'AniDB', 'name' => 'banned'])->update(['value' => time()]);
        } elseif (preg_match('/\<error\>Anime not found\<\/error\>/', $apiresponse)) {
            echo "AniDB   : Anime not yet on site. Remove until next update.\n";
        } elseif ($AniDBAPIXML = new \SimpleXMLElement($apiresponse)) {
            $AniDBAPIArray['similar'] = $this->processAPIResponseElement($AniDBAPIXML->similaranime, 'anime', false);
            $AniDBAPIArray['related'] = $this->processAPIResponseElement($AniDBAPIXML->relatedanime, 'anime', false);
            $AniDBAPIArray['creators'] = $this->processAPIResponseElement($AniDBAPIXML->creators, null, false);
            $AniDBAPIArray['characters'] = $this->processAPIResponseElement($AniDBAPIXML->characters, null, true);
            $AniDBAPIArray['categories'] = $this->processAPIResponseElement($AniDBAPIXML->categories, null, true);

            $episodeArray = [];
            if ($AniDBAPIXML->episodes && $AniDBAPIXML->episodes->episode[0]->attributes()) {
                $i = 1;
                foreach ($AniDBAPIXML->episodes->episode as $episode) {
                    $titleArray = [];

                    $episodeArray[$i]['episode_id'] = (int) $episode->attributes()->id;
                    $episodeArray[$i]['episode_no'] = (int) $episode->epno;
                    $episodeArray[$i]['airdate'] = (string) $episode->airdate;

                    if (! empty($episode->title)) {
                        foreach ($episode->title as $title) {
                            $xmlAttribs = $title->attributes('xml', true);
                            // only english, x-jat imploded episode titles for now
                            if (\in_array($xmlAttribs->lang, ['en', 'x-jat'], false)) {
                                $titleArray[] = $title[0];
                            }
                        }
                    }

                    $episodeArray[$i]['episode_title'] = empty($titleArray) ? '' : implode(', ', $titleArray);
                    $i++;
                }
            }

            //start and end date come from AniDB API as date strings -- no manipulation needed
            $AniDBAPIArray['startdate'] = $AniDBAPIXML->startdate ?? '0000-00-00';
            $AniDBAPIArray['enddate'] = $AniDBAPIXML->enddate ?? '0000-00-00';

            if (isset($AniDBAPIXML->ratings->permanent)) {
                $AniDBAPIArray['rating'] = $AniDBAPIXML->ratings->permanent;
            } else {
                $AniDBAPIArray['rating'] = $AniDBAPIXML->ratings->temporary ?? $AniDBAPIArray['rating'] = '';
            }

            $AniDBAPIArray += [
                'type'        => isset($AniDBAPIXML->type[0]) ? (string) $AniDBAPIXML->type : '',
                'description' => isset($AniDBAPIXML->description) ? (string) $AniDBAPIXML->description : '',
                'picture'     => isset($AniDBAPIXML->picture[0]) ? (string) $AniDBAPIXML->picture : '',
                'epsarr'      => $episodeArray,
            ];

            return $AniDBAPIArray;
        }

        return false;
    }

    /**
     * @param  \SimpleXMLElement  $element
     * @param  string  $property
     * @param  bool  $children
     * @return string
     */
    private function processAPIResponseElement(\SimpleXMLElement $element, $property = null, $children = false): string
    {
        $property = $property ?? 'name';
        $temp = '';

        if (\is_object($element) && ! empty($element)) {
            $result = $children === true ? $element->children() : $element;
            foreach ($result as $entry) {
                $temp .= (string) $entry->$property.', ';
            }
        }

        return empty($temp) ? '' : substr($temp, 0, -2);
    }

    /**
     * Requests and returns the API data from AniDB.
     *
     * @param $anidbId
     * @return string
     */
    private function getAniDbResponse($anidbId): string
    {
        $curlString = sprintf(
            'http://api.anidb.net:9001/httpapi?request=anime&client=%s&clientver=%d&protover=1&aid=%d',
            $this->apiKey,
            self::CLIENT_VERSION,
            $anidbId
        );

        $ch = curl_init($curlString);

        $curlOpts = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER         => 0,
            CURLOPT_FAILONERROR    => 1,
            CURLOPT_ENCODING       => 'gzip',
        ];

        curl_setopt_array($ch, $curlOpts);
        $apiresponse = curl_exec($ch);
        curl_close($ch);

        return $apiresponse;
    }

    /**
     * Inserts new anime info from AniDB to anidb table.
     *
     * @param  int  $id  The AniDB ID to be inserted
     * @param  string  $type  The title type
     * @param  string  $lang  The title language
     * @param  string  $title  The title of the Anime
     */
    private function insertAniDb($id, $type, $lang, $title): void
    {
        $check = AnidbTitle::query()->where(['anidbid' => $id, 'type' => $type, 'lang' => $lang, 'title' => $title])->first();

        if ($check === null) {
            AnidbTitle::insertOrIgnore(['anidbid' => $id, 'type' => $type, 'lang' => $lang, 'title' => $title]);
        } else {
            $this->colorCli->warning("Duplicate: $id");
        }
    }

    /**
     * Inserts new anime info from AniDB to anidb table.
     *
     * @param  array  $AniDBInfoArray
     * @return string
     */
    private function insertAniDBInfoEps(array $AniDBInfoArray = [], $anidbId): string
    {
        AnidbInfo::query()
            ->insert(
                [
                    'anidbid' => $anidbId,
                    'type' => $AniDBInfoArray['type'],
                    'startdate' => $AniDBInfoArray['startdate'],
                    'enddate' => $AniDBInfoArray['enddate'],
                    'related' => $AniDBInfoArray['enddate'],
                    'similar' => $AniDBInfoArray['similar'],
                    'creators' => $AniDBInfoArray['creators'],
                    'description' => $AniDBInfoArray['description'],
                    'rating' => $AniDBInfoArray['rating'],
                    'picture' => $AniDBInfoArray['picture'],
                    'categories' => $AniDBInfoArray['categories'],
                    'characters' => $AniDBInfoArray['characters'],
                    'updated' => now(),
                ]
            );
        if (! empty($AniDBInfoArray['epsarr'])) {
            $this->insertAniDBEpisodes($AniDBInfoArray['epsarr'], $anidbId);
        }

        return $AniDBInfoArray['picture'];
    }

    /**
     * Inserts new anime info from AniDB to anidb table.
     *
     * @param  array  $episodeArr
     */
    private function insertAniDBEpisodes(array $episodeArr = [], $anidbId): void
    {
        if (! empty($episodeArr)) {
            foreach ($episodeArr as $episode) {
                AnidbEpisode::insertOrIgnore(
                    [
                        'anidbid' => $anidbId,
                        'episodeid' => $episode['episode_id'],
                        'episode_no' => $episode['episode_no'],
                        'episode_title' => $episode['episode_title'],
                        'airdate' => $episode['airdate'],
                    ]
                );
            }
        }
    }

    /**
     *  Grabs AniDB Full Dump XML and inserts it into anidb table.
     */
    private function populateMainTable()
    {
        $lastUpdate = Carbon::createFromTimestamp($this->lastUpdate);
        $current = now();

        if ($current->diff($lastUpdate)->format('%d') > $this->updateInterval) {
            if ($this->echooutput) {
                $this->colorCli->header('Updating anime titles by grabbing full data AniDB dump.');
            }

            $animetitles = new \SimpleXMLElement('compress.zlib://http://anidb.net/api/anime-titles.xml.gz', null, true);

            //Even if the update process fails,
            //we must mark the last update time or risk ban
            $this->setLastUpdated();

            if ($animetitles instanceof \Traversable) {
                $count = $animetitles->count();
                if ($this->echooutput) {
                    $this->colorCli->header(
                        'Total of '.number_format($count).' titles to add.'.PHP_EOL
                    );
                }

                foreach ($animetitles as $anime) {
                    echo "Remaining: $count  \r";
                    foreach ($anime->title as $title) {
                        $xmlAttribs = $title->attributes('xml', true);
                        $this->insertAniDb(
                            (string) $anime['aid'],
                            (string) $title['type'],
                            (string) $xmlAttribs->lang,
                            (string) $title[0]
                        );
                        $this->colorCli->primary(
                            sprintf(
                                'Inserting: %d, %s, %s, %s',
                                $anime['aid'],
                                $title['type'],
                                $xmlAttribs->lang,
                                $title[0]
                            )
                        );
                    }
                    $count--;
                }
            } else {
                echo PHP_EOL.
                    $this->colorCli->error('Error retrieving XML data from AniDB. Please try again later.').
                    PHP_EOL;
            }
        } else {
            $this->colorCli->info(
                    'AniDB has been updated within the past '.$this->updateInterval.' days. '.
                    'Either set this value lower in Site Edit (at your own risk of being banned) or try again later.'
            );
        }
    }

    /**
     * Directs flow for populating the AniDB Info/Episodes table.
     *
     * @param  string  $anidbId
     *
     * @throws \Exception
     */
    private function populateInfoTable($anidbId = '')
    {
        if (empty($anidbId)) {
            $anidbIds = AnidbTitle::query()
                ->selectRaw('DISTINCT anidb_titles.anidbid')
                ->leftJoin('anidb_info as ai', 'ai.anidbid', '=', 'anidb_titles.anidbid')
                ->whereNull('ai.updated')
                ->get();

            foreach ($anidbIds as $anidb) {
                $AniDBAPIArray = $this->getAniDbAPI($anidb['anidbid']);

                if ($this->banned === true) {
                    $this->colorCli->error(
                            'AniDB Banned, import will fail, please wait 24 hours before retrying.'
                        );
                    exit;
                }

                if ($AniDBAPIArray === false && $this->echooutput) {
                    $this->colorCli->info(
                            'Anime ID: '.$anidb['anidbid'].' not available for update yet.'
                        );
                } else {
                    $this->updateAniChildTables($AniDBAPIArray, $anidb['anidbid']);
                }
                sleep(random_int(120, 240));
            }
        } else {
            $AniDBAPIArray = $this->getAniDbAPI($anidbId);

            if ($this->banned === true) {
                $this->colorCli->error(
                        'AniDB Banned, import will fail, please wait 24 hours before retrying.'
                    );
                exit;
            }

            if ($AniDBAPIArray === false && $this->echooutput) {
                $this->colorCli->info(
                        'Anime ID: '.$anidbId.' not available for update yet.'
                    );
            } else {
                $this->updateAniChildTables($AniDBAPIArray, $anidbId);
            }
        }
    }

    /**
     * Sets the database time for last full AniDB update.
     */
    private function setLastUpdated(): void
    {
        Settings::query()->where(['section' => 'APIs', 'subsection' => 'AniDB', 'name' => 'last_full_update'])->update(['value' => time()]);
    }

    /**
     * Updates existing anime info in anidb info/episodes tables.
     *
     * @param  array  $AniDBInfoArray
     * @return string
     */
    private function updateAniDBInfoEps(array $AniDBInfoArray = [], $anidbId): string
    {
        AnidbInfo::query()
            ->where('anidbid', $anidbId)
            ->update(
                [
                    'type' => $AniDBInfoArray['type'],
                    'startdate' => $AniDBInfoArray['startdate'],
                    'enddate' => $AniDBInfoArray['enddate'],
                    'related' => $AniDBInfoArray['enddate'],
                    'similar' => $AniDBInfoArray['similar'],
                    'creators' => $AniDBInfoArray['creators'],
                    'description' => $AniDBInfoArray['description'],
                    'rating' => $AniDBInfoArray['rating'],
                    'picture' => $AniDBInfoArray['picture'],
                    'categories' => $AniDBInfoArray['categories'],
                    'characters' => $AniDBInfoArray['characters'],
                    'updated' => now(),
                ]
            );
        if (! empty($AniDBInfoArray['epsarr'])) {
            $this->insertAniDBEpisodes($AniDBInfoArray['epsarr'], $anidbId);
        }

        return $AniDBInfoArray['picture'];
    }

    /**
     * Directs flow for updating child AniDB tables.
     *
     * @param  array  $AniDBInfoArray
     * @param  $anidbId
     */
    private function updateAniChildTables(array $AniDBInfoArray = [], $anidbId): void
    {
        $check = AnidbInfo::query()->where('anidbid', $anidbId)->first(['anidbid']);

        if ($check === null) {
            $picture = $this->insertAniDBInfoEps($AniDBInfoArray, $anidbId);
        } else {
            $picture = $this->updateAniDBInfoEps($AniDBInfoArray, $anidbId);
        }

        if (! empty($picture) && ! file_exists($this->imgSavePath.$anidbId.'.jpg')) {
            (new ReleaseImage())->saveImage(
                $anidbId,
                'http://img7.anidb.net/pics/anime/'.$picture,
                $this->imgSavePath
            );
        }
    }
}

<?php

namespace App\Services;

use App\Facades\Search;
use App\Models\Predb;
use App\Models\UsenetGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class IRCScraper.
 */
class IRCScraper extends IRCClient
{
    /**
     * Regex to ignore categories.
     */
    protected string|false $_categoryIgnoreRegex = false;

    /**
     * Array of current pre info.
     *
     * @var array<string, mixed>
     */
    protected array $_curPre;

    /**
     * List of groups and their id's.
     *
     * @var array<string, mixed>
     */
    protected array $_groupList;

    /**
     * Array of ignored channels.
     *
     * @var array<string, mixed>
     */
    protected array $_ignoredChannels;

    /**
     * Is this pre nuked or un nuked?
     */
    protected bool $_nuked;

    protected mixed $_oldPre;

    /**
     * Run this in silent mode (no text output).
     */
    protected bool $_silent;

    /**
     * Regex to ignore PRE titles.
     */
    protected string|false $_titleIgnoreRegex = false;

    /**
     * Construct.
     *
     * @param  bool  $silent  Run this in silent mode (no text output).
     * @param  bool  $debug  Turn on debug? Shows sent/received socket buffer messages.
     *
     * @throws \Exception
     */
    public function __construct(bool $silent, bool $debug)
    {
        if (config('irc_settings.scrape_irc_source_ignore')) {
            try {
                $ignored = unserialize(
                    (string) config('irc_settings.scrape_irc_source_ignore'),
                    ['allowed_classes' => false]
                );
                $this->_ignoredChannels = is_array($ignored) ? $ignored : [];
            } catch (\ValueError $e) {
                $this->_ignoredChannels = [];
            }
        } else {
            $this->_ignoredChannels = [
                '#a.b.cd.image' => false,
                '#a.b.console.ps3' => false,
                '#a.b.dvd' => false,
                '#a.b.erotica' => false,
                '#a.b.flac' => false,
                '#a.b.foreign' => false,
                '#a.b.games.nintendods' => false,
                '#a.b.inner-sanctum' => false,
                '#a.b.moovee' => false,
                '#a.b.movies.divx' => false,
                '#a.b.sony.psp' => false,
                '#a.b.sounds.mp3.complete_cd' => false,
                '#a.b.teevee' => false,
                '#a.b.games.wii' => false,
                '#a.b.warez' => false,
                '#a.b.games.xbox360' => false,
                '#pre@corrupt' => false,
                '#scnzb' => false,
                '#tvnzb' => false,
                'srrdb' => false,
            ];
        }

        if (config('irc_settings.scrape_irc_category_ignore') !== '') {
            $this->_categoryIgnoreRegex = (string) config('irc_settings.scrape_irc_category_ignore');
        }

        if (config('irc_settings.scrape_irc_title_ignore') !== '') {
            $this->_titleIgnoreRegex = (string) config('irc_settings.scrape_irc_title_ignore');
        }

        $this->_groupList = [];
        $this->_silent = $silent;
        $this->_debug = $debug;
        $this->_resetPreVariables();
        $this->_startScraping();
    }

    /**
     * Main method for scraping.
     */
    protected function _startScraping(): void
    {
        // Connect to IRC.
        if ($this->connect((string) config('irc_settings.scrape_irc_server'), (int) config('irc_settings.scrape_irc_port'), (bool) config('irc_settings.scrape_irc_tls')) === false) {
            exit(
                'Error connecting to ('.
                config('irc_settings.scrape_irc_server').
                ':'.
                config('irc_settings.scrape_irc_port').
                '). Please verify your server information and try again.'.
                PHP_EOL
            );
        }

        // Normalize password to ?string
        $password = config('irc_settings.scrape_irc_password');
        $password = ($password === false || $password === '' || $password === null) ? null : (string) $password;

        // Login to IRC. Note parameter order: nick, user, real, pass.
        if ($this->login((string) config('irc_settings.scrape_irc_nickname'), (string) config('irc_settings.scrape_irc_username'), (string) config('irc_settings.scrape_irc_realname'), $password) === false) {
            exit(
                'Error logging in to: ('.
                config('irc_settings.scrape_irc_server').':'.config('irc_settings.scrape_irc_port').') nickname: ('.config('irc_settings.scrape_irc_nickname').
                '). Verify your connection information, you might also be banned from this server or there might have been a connection issue.'.
                PHP_EOL
            );
        }

        // Join channels.
        $channelsCfg = config('irc_settings.scrape_irc_channels');
        if ($channelsCfg) {
            try {
                $channels = unserialize((string) $channelsCfg, ['allowed_classes' => false]);
            } catch (\ValueError $e) {
                $channels = ['#PreNNTmux' => null];
            }
            if (! is_array($channels)) {
                $channels = ['#PreNNTmux' => null];
            }
        } else {
            $channels = ['#PreNNTmux' => null];
        }
        $this->joinChannels($channels);

        if (! $this->_silent) {
            echo '['.
                date('r').
                '] [Scraping of IRC channels for ('.
                config('irc_settings.scrape_irc_server').
                ':'.
                config('irc_settings.scrape_irc_port').
                ') ('.
                config('irc_settings.scrape_irc_nickname').
                ') started.]'.
                PHP_EOL;
        }

        // Scan incoming IRC messages.
        $this->readIncoming();
    }

    /**
     * Process bot messages, insert/update PREs.
     *
     * @throws \Exception
     */
    protected function processChannelMessages(): void
    {
        if ($this->_debug && ! $this->_silent) {
            echo '[DEBUG] Processing message: '.$this->_channelData['message'].PHP_EOL;
        }

        if (preg_match(
            '/^(NEW|UPD|NUK): \[DT: (?P<time>.+?)\]\s?\[TT: (?P<title>.+?)\]\s?\[SC: (?P<source>.+?)\]\s?\[CT: (?P<category>.+?)\]\s?\[RQ: (?P<req>.+?)\]'.
            '\s?\[SZ: (?P<size>.+?)\]\s?\[FL: (?P<files>.+?)\]\s?(\[FN: (?P<filename>.+?)\]\s?)?(\[(?P<nuked>(UN|MOD|RE|OLD)?NUKED?): (?P<reason>.+?)\])?$/i',
            $this->_channelData['message'],
            $hits
        )) {
            if ($this->_debug && ! $this->_silent) {
                echo '[DEBUG] Regex matched! Title: '.$hits['title'].' | Source: '.$hits['source'].' | Category: '.$hits['category'].PHP_EOL;
            }

            if (isset($this->_ignoredChannels[$hits['source']]) && $this->_ignoredChannels[$hits['source']] === true) {
                if ($this->_debug && ! $this->_silent) {
                    echo '[DEBUG] Source '.$hits['source'].' is ignored, skipping...'.PHP_EOL;
                }

                return;
            }

            if ($this->_categoryIgnoreRegex !== false && preg_match((string) $this->_categoryIgnoreRegex, $hits['category'])) {
                if ($this->_debug && ! $this->_silent) {
                    echo '[DEBUG] Category '.$hits['category'].' is ignored by regex, skipping...'.PHP_EOL;
                }

                return;
            }

            if ($this->_titleIgnoreRegex !== false && preg_match((string) $this->_titleIgnoreRegex, $hits['title'])) {
                if ($this->_debug && ! $this->_silent) {
                    echo '[DEBUG] Title '.$hits['title'].' is ignored by regex, skipping...'.PHP_EOL;
                }

                return;
            }

            $utime = Carbon::createFromTimeString($hits['time'], 'UTC')->timestamp;

            $this->_curPre['predate'] = 'FROM_UNIXTIME('.$utime.')';
            $this->_curPre['title'] = $hits['title'];
            $this->_curPre['source'] = $hits['source'];
            if ($hits['category'] !== 'N/A') {
                $this->_curPre['category'] = $hits['category'];
            }
            if ($hits['req'] !== 'N/A' && preg_match('/^(?P<req>\d+):(?P<group>.+)$/i', $hits['req'], $matches2)) {
                $this->_curPre['reqid'] = $matches2['req'];
                $this->_curPre['group_id'] = $this->_getGroupID($matches2['group']);
            }
            if ($hits['size'] !== 'N/A') {
                $this->_curPre['size'] = $hits['size'];
            }
            if ($hits['files'] !== 'N/A') {
                $this->_curPre['files'] = substr($hits['files'], 0, 50);
            }

            if (isset($hits['filename']) && $hits['filename'] !== 'N/A') {
                $this->_curPre['filename'] = $hits['filename'];
            }

            if (isset($hits['nuked'])) {
                switch ($hits['nuked']) {
                    case 'NUKED':
                        $this->_curPre['nuked'] = Predb::PRE_NUKED;
                        break;
                    case 'UNNUKED':
                        $this->_curPre['nuked'] = Predb::PRE_UNNUKED;
                        break;
                    case 'MODNUKED':
                        $this->_curPre['nuked'] = Predb::PRE_MODNUKE;
                        break;
                    case 'RENUKED':
                        $this->_curPre['nuked'] = Predb::PRE_RENUKED;
                        break;
                    case 'OLDNUKE':
                        $this->_curPre['nuked'] = Predb::PRE_OLDNUKE;
                        break;
                }
                $this->_curPre['reason'] = (isset($hits['reason']) ? substr($hits['reason'], 0, 255) : '');
                $this->_nuked = true; // flag for output
            }
            $this->_checkForDupe();
        } else {
            if ($this->_debug && ! $this->_silent) {
                echo '[DEBUG] Message did not match PRE regex pattern'.PHP_EOL;
            }
        }
    }

    /**
     * Check if we already have the PRE, update if we have it, insert if not.
     *
     * @throws \Exception
     */
    protected function _checkForDupe(): void
    {
        $this->_oldPre = Predb::query()->where('title', $this->_curPre['title'])->select(['category', 'size'])->first();
        if ($this->_oldPre === null) {
            if ($this->_debug && ! $this->_silent) {
                echo '[DEBUG] New PRE found, inserting: '.$this->_curPre['title'].PHP_EOL;
            }
            $this->_insertNewPre();
        } else {
            if ($this->_debug && ! $this->_silent) {
                echo '[DEBUG] PRE already exists, updating: '.$this->_curPre['title'].PHP_EOL;
            }
            $this->_updatePre();
        }
        $this->_resetPreVariables();
    }

    /**
     * Insert new PRE into the DB.
     *
     *
     * @throws \Exception
     */
    protected function _insertNewPre(): void
    {
        // Check if title is empty first
        if (empty($this->_curPre['title'])) {
            if ($this->_debug && ! $this->_silent) {
                echo '[DEBUG] PRE title is empty, skipping insert'.PHP_EOL;
            }

            return;
        }

        // Double-check database to ensure we don't have stale search index data
        $existingPre = Predb::query()->where('title', $this->_curPre['title'])->first();
        if ($existingPre !== null) {
            if ($this->_debug && ! $this->_silent) {
                echo '[DEBUG] PRE already exists in database (ID: '.$existingPre->id.'), skipping insert'.PHP_EOL;
            }

            return;
        }

        if ($this->_debug && ! $this->_silent) {
            echo '[DEBUG] PRE not in database, proceeding with insert...'.PHP_EOL;
        }

        $query = 'INSERT INTO predb (';

        $query .= (! empty($this->_curPre['size']) ? 'size, ' : '');
        $query .= (! empty($this->_curPre['category']) ? 'category, ' : '');
        $query .= (! empty($this->_curPre['source']) ? 'source, ' : '');
        $query .= (! empty($this->_curPre['reason']) ? 'nukereason, ' : '');
        $query .= (! empty($this->_curPre['files']) ? 'files, ' : '');
        $query .= (! empty($this->_curPre['reqid']) ? 'requestid, ' : '');
        $query .= (! empty($this->_curPre['group_id']) ? 'groups_id, ' : '');
        $query .= (! empty($this->_curPre['nuked']) ? 'nuked, ' : '');
        $query .= (! empty($this->_curPre['filename']) ? 'filename, ' : '');

        $query .= 'predate, title) VALUES (';

        $query .= (! empty($this->_curPre['size']) ? escapeString($this->_curPre['size']).', ' : '');
        $query .= (! empty($this->_curPre['category']) ? escapeString($this->_curPre['category']).', ' : '');
        $query .= (! empty($this->_curPre['source']) ? escapeString($this->_curPre['source']).', ' : '');
        $query .= (! empty($this->_curPre['reason']) ? escapeString($this->_curPre['reason']).', ' : '');
        $query .= (! empty($this->_curPre['files']) ? escapeString($this->_curPre['files']).', ' : '');
        $query .= (! empty($this->_curPre['reqid']) ? $this->_curPre['reqid'].', ' : '');
        $query .= (! empty($this->_curPre['group_id']) ? $this->_curPre['group_id'].', ' : '');
        $query .= (! empty($this->_curPre['nuked']) ? $this->_curPre['nuked'].', ' : '');
        $query .= (! empty($this->_curPre['filename']) ? escapeString($this->_curPre['filename']).', ' : '');
        $query .= (! empty($this->_curPre['predate']) ? $this->_curPre['predate'].', ' : 'NOW(), ');

        $query .= '%s)';

        if ($this->_debug && ! $this->_silent) {
            echo '[DEBUG] Executing SQL: '.substr($query, 0, 100).'...'.PHP_EOL;
        }

        try {
            DB::insert(
                sprintf(
                    $query,
                    escapeString($this->_curPre['title'])
                )
            );

            $lastId = DB::connection()->getPdo()->lastInsertId();

            if ($this->_debug && ! $this->_silent) {
                echo '[DEBUG] Successfully inserted PRE with ID: '.$lastId.PHP_EOL;
            }

            $parameters = [
                'id' => $lastId,
                'title' => $this->_curPre['title'],
                'filename' => $this->_curPre['filename'] ?? null,
                'source' => $this->_curPre['source'] ?? null,
            ];

            Search::insertPredb($parameters);

            $this->_doEcho(true);
        } catch (\Exception $e) {
            if ($this->_debug && ! $this->_silent) {
                echo '[DEBUG] ERROR inserting PRE: '.$e->getMessage().PHP_EOL;
            }
        }
    }

    /**
     * Updates PRE data in the DB.
     *
     *
     * @throws \Exception
     */
    protected function _updatePre(): void
    {
        if (empty($this->_curPre['title'])) {
            return;
        }

        $query = 'UPDATE predb SET ';

        $query .= (! empty($this->_curPre['size']) ? 'size = '.escapeString($this->_curPre['size']).', ' : '');
        $query .= (! empty($this->_curPre['source']) ? 'source = '.escapeString($this->_curPre['source']).', ' : '');
        $query .= (! empty($this->_curPre['files']) ? 'files = '.escapeString($this->_curPre['files']).', ' : '');
        $query .= (! empty($this->_curPre['reason']) ? 'nukereason = '.escapeString($this->_curPre['reason']).', ' : '');
        $query .= (! empty($this->_curPre['reqid']) ? 'requestid = '.$this->_curPre['reqid'].', ' : '');
        $query .= (! empty($this->_curPre['group_id']) ? 'groups_id = '.$this->_curPre['group_id'].', ' : '');
        $query .= (! empty($this->_curPre['predate']) ? 'predate = '.$this->_curPre['predate'].', ' : '');
        $query .= (! empty($this->_curPre['nuked']) ? 'nuked = '.$this->_curPre['nuked'].', ' : '');
        $query .= (! empty($this->_curPre['filename']) ? 'filename = '.escapeString($this->_curPre['filename']).', ' : '');
        $query .= (
            (empty($this->_oldPre['category']) && ! empty($this->_curPre['category']))
                ? 'category = '.escapeString($this->_curPre['category']).', '
                : ''
        );

        if ($query === 'UPDATE predb SET ') {
            return;
        }

        $query .= 'title = '.escapeString($this->_curPre['title']);
        $query .= ' WHERE title = '.escapeString($this->_curPre['title']);

        // Execute the update and then fetch the affected row ID by title.
        DB::update($query);

        // Look up the predb row ID by title for indexing backends.
        $predbId = Predb::query()->where('title', $this->_curPre['title'])->value('id');

        if (! empty($predbId)) {
            $parameters = [
                'id' => $predbId,
                'title' => $this->_curPre['title'],
                'filename' => $this->_curPre['filename'] ?? null,
                'source' => $this->_curPre['source'] ?? null,
            ];

            Search::updatePreDb($parameters);
        }

        $this->_doEcho(false);
    }

    /**
     * Echo new or update pre to CLI.
     */
    protected function _doEcho(bool $new = true): void
    {
        if (! $this->_silent) {
            $nukeString = '';
            if ($this->_nuked !== false) {
                switch ((int) $this->_curPre['nuked']) {
                    case Predb::PRE_NUKED:
                        $nukeString = '[ NUKED ] ';
                        break;
                    case Predb::PRE_UNNUKED:
                        $nukeString = '[UNNUKED] ';
                        break;
                    case Predb::PRE_MODNUKE:
                        $nukeString = '[MODNUKE] ';
                        break;
                    case Predb::PRE_OLDNUKE:
                        $nukeString = '[OLDNUKE] ';
                        break;
                    case Predb::PRE_RENUKED:
                        $nukeString = '[RENUKED] ';
                        break;
                    default:
                        break;
                }
                $nukeString .= '['.($this->_curPre['reason'] ?? '').'] ';
            }

            echo '['.
                date('r').
                ($new ? '] [ Added Pre ] [' : '] [Updated Pre] [').
                ($this->_curPre['source'] ?? '').
                '] '.
                $nukeString.
                '['.
                $this->_curPre['title'].
                ']'.
                (
                    ! empty($this->_curPre['category'])
                    ? ' ['.$this->_curPre['category'].']'
                    : (
                        ! empty($this->_oldPre['category'])
                        ? ' ['.$this->_oldPre['category'].']'
                        : ''
                    )
                ).
                (! empty($this->_curPre['size']) ? ' ['.$this->_curPre['size'].']' : '').
                PHP_EOL;
        }
    }

    /**
     * Get a group id for a group name.
     */
    protected function _getGroupID(string $groupName): mixed
    {
        if (! isset($this->_groupList[$groupName])) {
            $group = UsenetGroup::query()->where('name', $groupName)->first(['id']);
            $this->_groupList[$groupName] = $group !== null ? $group['id'] : '';
        }

        return $this->_groupList[$groupName];
    }

    /**
     * After updating or inserting new PRE, reset these.
     */
    protected function _resetPreVariables(): void
    {
        $this->_nuked = false;
        $this->_oldPre = [];
        $this->_curPre =
            [
                'title' => '',
                'size' => '',
                'predate' => '',
                'category' => '',
                'source' => '',
                'group_id' => '',
                'reqid' => '',
                'nuked' => '',
                'reason' => '',
                'files' => '',
                'filename' => '',
            ];
    }
}

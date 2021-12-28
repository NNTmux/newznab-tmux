<?php

namespace Blacklight;

use Illuminate\Support\Facades\DB;

/**
 * Class Sharing.
 */
class Sharing
{
    /**
     *      --------------------------------------------
     *      sharing_sites table (contains remote sites):
     *      --------------------------------------------
     *      id            id of the site.
     *      site_name     Name of the site.
     *      site_guid     Unique hash identifier for the site.
     *      last_time     Newest comment time for this site.
     *      first_time    Oldest comment time for this site.
     *      enabled       Have we enabled this site?
     *      comments      How many comments has this site given us so far?
     *
     *      -------------------------------------------
     *      sharing table (contains local settings):
     *      -------------------------------------------
     *      site_guid     Unique identifier for our site.
     *      site_name     Our site name.
     *      enabled       Is sharing/fetching enabled or disabled (overrides settings below)?
     *      posting       Should we upload our comments?
     *      fetching      Should we fetch remote comments?
     *      auto_enable   Should we auto_enable new sites?
     *      hide_users    Hide usernames before uploading comments?
     *      last_article  Last article number we downloaded from usenet.
     *      max_push      Max comments to upload per run.
     *      max_pull      Max articles to download per run.
     *
     *      -------------------------------------------
     *      release_comments table (modifications)
     *      -------------------------------------------
     *      shared        Has this comment been shared or have we received it from another site. (0 not shared, 1 shared, 2 received)
     *      shareid       Unique identifier to know if we already have the comment or not.
     *      nzb_guid      Guid of the NZB's first message-id.
     */

    /**
     * @var \Blacklight\NNTP
     */
    protected $nntp;

    /**
     * Array containing site settings.
     *
     * @var array
     */
    protected $siteSettings = [];

    /**
     * Group to work in.
     *
     * @const
     */
    private const group = 'alt.binaries.zines';

    /**
     * Construct.
     *
     * @param  array  $options  Class instances.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
            'NNTP'     => null,
        ];
        $options += $defaults;

        $this->nntp = ($options['NNTP'] instanceof NNTP ? $options['NNTP'] : new NNTP());

        // Get all sharing info from DB.
        $check = DB::selectOne('SELECT * FROM sharing');

        // Initiate sharing settings if this is the first time..
        if (empty($check)) {
            $check = $this->initSettings();
        }

        // Second check to make sure nothing went wrong.
        if (empty($check)) {
            return;
        }

        // Cache sharing settings.
        $this->siteSettings = $check;
        unset($check);

        // Convert to bool to speed up checking.
        $this->siteSettings->hide_users = (int) $this->siteSettings->hide_users === 1;
        $this->siteSettings->auto_enable = (int) $this->siteSettings->auto_enable === 1;
        $this->siteSettings->posting = (int) $this->siteSettings->posting === 1;
        $this->siteSettings->fetching = (int) $this->siteSettings->fetching === 1;
        $this->siteSettings->enabled = (int) $this->siteSettings->enabled === 1;
        $this->siteSettings->start_position = (int) $this->siteSettings->start_position === 1;
    }

    /**
     * Main method.
     *
     * @throws \Exception
     */
    public function start(): void
    {
        // Admin has disabled sharing so return.
        if ($this->siteSettings->enabled === false) {
            return;
        }

        if ($this->nntp === null) {
            $this->nntp = new NNTP();
            $this->nntp->doConnect();
        }

        if ($this->siteSettings->fetching) {
            $this->fetchAll();
        }
        $this->matchComments();
        if ($this->siteSettings->posting) {
            $this->postAll();
            $this->postSC();
        }
    }

    /**
     * Initialise of reset sharing settings.
     *
     *
     * @param  string  $siteGuid  Optional hash (must be sha1) we can set the site guid to.
     * @return mixed
     */
    public function initSettings(&$siteGuid = '')
    {
        DB::statement('TRUNCATE TABLE sharing');
        $siteName = uniqid('nntmux_', true);
        DB::insert(
            sprintf(
                '
				INSERT INTO sharing
				(site_name, site_guid, max_push, max_pull, hide_users, start_position, auto_enable, fetching, max_download)
				VALUES (%s, %s, 40 , 20000, 1, 1, 1, 1, 150)',
                escapeString($siteName),
                escapeString(($siteGuid === '' ? sha1($siteName) : $siteGuid))
            )
        );

        return DB::selectOne('SELECT * FROM sharing');
    }

    /**
     * Post all new comments to usenet.
     */
    protected function postAll()
    {
        // Get all comments that we have not posted yet.
        $newComments = DB::select(
            sprintf(
                'SELECT rc.text, rc.id, UNIX_TIMESTAMP(rc.created_at) AS unix_time, u.username, HEX(r.nzb_guid) AS nzb_guid
				FROM release_comments rc
				INNER JOIN users u ON rc.users_id = u.id
				INNER JOIN releases r on rc.releases_id = r.id
				WHERE (rc.shared = 0 or issynced = 1) LIMIT %d',
                $this->siteSettings['max_push']
            )
        );

        // Check if we have any comments to push.
        if (\count($newComments) === 0) {
            return;
        }

        echo '(Sharing) Starting to upload comments.'.PHP_EOL;

        // Loop over the comments.
        foreach ($newComments as $comment) {
            $this->postComment($comment);
        }

        echo PHP_EOL.'(Sharing) Finished uploading comments.'.PHP_EOL;
    }

    /**
     * @throws \Exception
     */
    protected function postSC()
    {
        // Get all comments from spotnab that we have not posted yet.
        $newComments = DB::select(
            sprintf(
                'SELECT id, text, UNIX_TIMESTAMP(created_at) AS unix_time,
				username, nzb_guid FROM release_comments
				WHERE issynced = 1 AND shared = 1 AND shareid = ""
				ORDER BY id DESC'
            )
        );

        // Check if we have any comments to push.
        if (\count($newComments) === 0) {
            return;
        }

        echo '(Sharing) Starting to upload spotnab comments.'.PHP_EOL;

        // Loop over the comments.
        foreach ($newComments as $comment) {
            $this->postComment($comment);
        }

        echo PHP_EOL.'(Sharing) Finished uploading spotnab comments.'.PHP_EOL;
    }

    /**
     * @param $row
     *
     * @throws \Exception
     */
    protected function postComment(&$row)
    {
        // Create a unique identifier for this comment.
        $sid = sha1($row->unix_time.$row->text.$row->nzb_guid);

        // Check if the comment is already shared.
        $check = DB::selectOne(sprintf('SELECT id FROM release_comments WHERE shareid = %s', escapeString($sid)));
        if ($check === null) {

            // Example of a subject.
            //(_nZEDb_)nZEDb_533f16e46a5091.73152965_3d12d7c1169d468aaf50d5541ef02cc88f3ede10 - [1/1] "92ba694cebc4fbbd0d9ccabc8604c71b23af1131" (1/1) yEnc

            // Attempt to upload the comment to usenet.
            $success = $this->nntp->postArticle(
                self::group,
                '(_nZEDb_)'.$this->siteSettings->site_name.'_'.$this->siteSettings->site_guid.' - [1/1] "'.$sid.'" yEnc (1/1)',
                json_encode(
                    [
                        'USER' => $this->siteSettings->hide_users ? 'ANON' : $row->username,
                        'TIME' => $row->unix_time,
                        'SID'  => $sid,
                        'RID'  => $row->nzb_guid,
                        'BODY' => $row->text,
                    ]
                ),
                '<anon@anon.com>'
            );

            // Check if we succesfully uploaded it.
            if ($success === true && $this->nntp->isError($success) === false) {

                // Update DB to say we posted the article.
                DB::update(
                    sprintf(
                        '
						UPDATE release_comments
						SET shared = 1, shareid = %s
						WHERE id = %d',
                        escapeString($sid),
                        $row->id
                    )
                );

                echo '.';
            }
        } else {
            // Update the DB to say it's shared.
            DB::update(sprintf('UPDATE release_comments SET shared = 1 WHERE id = %d', $row->id));
        }
    }

    /**
     * Match added comments to releases.
     */
    protected function matchComments()
    {
        $res = DB::select(
            '
			SELECT r.id
			FROM release_comments rc
			INNER JOIN releases r USING (nzb_guid)
			WHERE rc.releases_id = 0'
        );
        $found = \count($res);
        if ($found > 0) {
            foreach ($res as $row) {
                DB::update(
                    sprintf(
                        '
						UPDATE release_comments rc
						INNER JOIN releases r USING (nzb_guid)
						SET rc.releases_id = %d, r.comments = r.comments + 1
						WHERE r.id = %d
						AND rc.releases_id = 0',
                        $row->id,
                        $row->id
                    )
                );
            }
            if (config('nntmux.echocli')) {
                echo "(Sharing) Matched $found  comments.".PHP_EOL;
            }
        }

        // Update first time seen.
        DB::update(
        sprintf(
            "
					UPDATE sharing_sites ss
					INNER JOIN
						(SELECT siteid, created_at
						FROM release_comments
						WHERE created_at > '2005-01-01'
						GROUP BY siteid
						ORDER BY created_at ASC) rc
					ON ss.site_guid = rc.siteid
					SET ss.first_time = rc.created_at
					WHERE ss.first_time IS NULL OR ss.first_time > rc.created_at"
        )
    );
    }

    /**
     * Get all new comments from usenet.
     */
    protected function fetchAll()
    {
        // Get NNTP group data.
        $group = $this->nntp->selectGroup(self::group, false, true);

        // Check if there's an issue.
        if ($this->nntp->isError($group)) {
            return;
        }

        // Check if this is the first time, set our oldest article.
        if ((int) $this->siteSettings->last_article === 0) {
            // If the user picked to start from the oldest, get the oldest.
            if ($this->siteSettings->start_position === true) {
                $this->siteSettings->last_article = $ourOldest = $group['first'];
            // Else get the newest.
            } else {
                $this->siteSettings->last_article = $ourOldest = (string) ($group['last'] - $this->siteSettings->max_download);
                if ($ourOldest < $group['first']) {
                    $this->siteSettings->last_article = $ourOldest = $group['first'];
                }
            }
        } else {
            $ourOldest = (string) ($this->siteSettings->last_article + 1);
        }

        // Set our newest to our oldest wanted + max pull setting.
        $newest = (string) ($ourOldest + $this->siteSettings->max_pull);

        // Check if our newest wanted is newer than the group's newest, set to group's newest.
        if ($newest >= $group['last']) {
            $newest = $group['last'];
        }

        // We have nothing to do, so return.
        if ($ourOldest > $newest) {
            return;
        }

        if (config('nntmux.echocli')) {
            echo '(Sharing) Starting to fetch new comments.'.PHP_EOL;
        }

        // Get the wanted aritcles
        $headers = $this->nntp->getOverview($ourOldest.'-'.$newest, true, false);

        // Check if we received nothing or there was an error.
        if ($this->nntp->isError($headers) || \count($headers) === 0) {
            return;
        }

        $found = $total = $currentArticle = 0;
        // Loop over NNTP headers until we find comments.
        foreach ($headers as $header) {

            // Check if the article is missing.
            if (! isset($header['Number'])) {
                continue;
            }

            // Get the current article number.
            $currentArticle = $header['Number'];

            // Break out of the loop if we have downloaded more comments than the user wants.
            if ($found > $this->siteSettings->max_download) {
                break;
            }

            $hits = [];
            //(_nZEDb_)nZEDb_533f16e46a5091.73152965_3d12d7c1169d468aaf50d5541ef02cc88f3ede10 - [1/1] "92ba694cebc4fbbd0d9ccabc8604c71b23af1131" (1/1) yEnc
            if ($header['From'] === '<anon@anon.com>' &&
                preg_match('/^\(_nZEDb_\)(?P<site>.+?)_(?P<guid>[a-f0-9]{40}) - \[1\/1\] "(?P<sid>[a-f0-9]{40})" yEnc \(1\/1\)$/i', $header['Subject'], $hits)) {

                // Check if this is from our own site.
                if ($hits['guid'] === $this->siteSettings->site_guid) {
                    continue;
                }

                // Check if we already have the comment.
                $check = DB::selectOne(
                    sprintf(
                        'SELECT id FROM release_comments WHERE shareid = %s',
                        escapeString($hits['sid'])
                    )
                );

                // We don't have it, so insert it.
                if ($check === null) {

                    // Check if we have the site and if it is enabled.
                    $check = DB::selectOne(
                        sprintf(
                            'SELECT enabled FROM sharing_sites WHERE site_guid = %s',
                            escapeString($hits['guid'])
                        )
                    );

                    if ($check === null) {
                        // Check if the user has auto enable on.
                        if ($this->siteSettings->auto_enable === false) {
                            // Insert the site so the admin can enable it later on.
                            DB::insert(
                                sprintf(
                                    '
									INSERT INTO sharing_sites
									(site_name, site_guid, last_time, first_time, enabled, comments)
									VALUES (%s, %s, NOW(), NOW(), 0, 0)',
                                    escapeString($hits['site']),
                                    escapeString($hits['guid'])
                                )
                            );
                            continue;
                        }

                        // Insert the site as enabled since the user has auto enabled on.
                        DB::insert(
                            sprintf(
                                '
                                INSERT INTO sharing_sites
                                (site_name, site_guid, last_time, first_time, enabled, comments)
                                VALUES (%s, %s, NOW(), NOW(), 1, 0)',
                                escapeString($hits['site']),
                                escapeString($hits['guid'])
                            )
                        );
                    } elseif ((int) $check->enabled === 0) {
                        continue;
                    }

                    // Insert the comment, if we got it, update the site to increment comment count.
                    if ($this->insertNewComment($header['Message-ID'], $hits['guid'])) {
                        DB::update(
                            sprintf(
                                '
								UPDATE sharing_sites SET comments = comments + 1, last_time = NOW(), site_name = %s WHERE site_guid = %s',
                                escapeString($hits['site']),
                                escapeString($hits['guid'])
                            )
                        );
                        $found++;
                        if (config('nntmux.echocli')) {
                            echo '.';
                            if ($found % 40 === 0) {
                                echo '['.$found.']'.PHP_EOL;
                            }
                        }
                    }
                }
            }
            // Update once in a while in case the user cancels the script.
            if ($total++ % 10 === 0) {
                $this->siteSettings->lastarticle = $currentArticle;
                DB::update(sprintf('UPDATE sharing SET last_article = %d', $currentArticle));
            }
        }

        if ($currentArticle > 0) {
            // Update sharing's last article number.
            $this->siteSettings['lastarticle'] = $currentArticle;
            DB::update(sprintf('UPDATE sharing SET last_article = %d', $currentArticle));
        }

        if (config('nntmux.echocli')) {
            if ($found > 0) {
                echo PHP_EOL.'(Sharing) Fetched '.$found.' new comments.'.PHP_EOL;
            } else {
                echo '(Sharing) Finish looking for new comments, but did not find any.'.PHP_EOL;
            }
        }
    }

    /**
     * Fetch a comment and insert it.
     *
     * @param  string  $messageID  Message-ID for the article.
     * @param  string  $siteID  id of the site.
     * @return bool
     *
     * @throws \Exception
     */
    protected function insertNewComment(&$messageID, &$siteID): bool
    {
        // Get the article body.
        $body = $this->nntp->getMessages(self::group, $messageID);

        // Check if there's an error.
        if ($this->nntp->isError($body)) {
            return false;
        }

        // Decompress the body.
        $body = @gzinflate($body);
        if ($body === false) {
            return false;
        }

        // JSON Decode the body.
        $body = json_decode($body, true);
        if ($body === false) {
            return false;
        }

        // Just in case.
        if (! isset($body['USER'], $body['SID']) || ! isset($body['RID']) || ! isset($body['TIME']) | ! isset($body['BODY'])) {
            return false;
        }
        $cid = md5($body['SID'].$body['USER'].$body['TIME'].$siteID);

        // Insert the comment.
        $unixTime = $body['TIME'] > time() ? time() : $body['TIME'];
        if (DB::insert(
            sprintf(
                '
				INSERT IGNORE INTO release_comments
				(text, created_at, issynced, shareid, cid, gid, nzb_guid, siteid, username, users_id, releases_id, shared, host, sourceID)
				VALUES (%s, FROM_UNIXTIME(%s), 1, %s, %s, %s, %s, %s, %s, 0, 0, 2, "", 999)',
                escapeString($body['BODY']),
                $unixTime,
                escapeString($body['SID']),
                escapeString($cid),
                escapeString($body['RID']),
                escapeString($body['RID']),
                escapeString($siteID),
                escapeString((strpos($body['USER'] === 0, 'sn-') !== false ? 'SH_ANON' : 'SH_'.$body['USER']))
            )
        )
        ) {
            return true;
        }

        return false;
    }
}

<h1>{$page->title}</h1>

<form action="{$SCRIPT_NAME}?action=submit" method="post">

    {if $error != ''}
        <div class="error">{$error}</div>
    {/if}

    <fieldset>
        <legend>Tmux - How It Works</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="explain">Information:</label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                <td>
                    <div class="explanation">Tmux is a screen multiplexer and at least version 1.6 is required. It is used here to allow multiple windows per session and multiple panes per window.<br /><br />
                        Each script is run in its own shell environment. It is not looped, but allowed to run once and then exit. This notifies tmux that the pane is dead and can then be respawned with another iteration of the script in a new shell environment. <br />
                        This allows for scripts that crash to be restarted without user intervention.<br /><br />
                        You can run multiple tmux sessions, but they all must have an associated tmux.conf file and all sessions must use the same tmux.conf file.<br /><br />
                        <h3><b>NOTICE:</b></h3> If "Save Tmux Settings" is the last thing you did on this page or if it is the active element and if you have this page set to autorefresh or you refresh instead of following a link to this page, you will set the db with the settings currently on this page, not reload from db. This could cause tmux scripts to start while optimize or patch the database is running.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Monitor</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="running">Tmux Scripts Running:</label></td>
                <td>
                    {html_radios id="running" name='running' values=$yesno_ids output=$yesno_names selected=$ftmux->running separator='<br />'}
                    <div class="hint">This is the shutdown, true/on, it runs, false/off and all scripts are terminated. This will start/stop all panes without terminating the monitor pane. This is not instant, it does not kill any panes, it simply does not aloow any pane to restart if set to false.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="monitor_delay">Monitor Loop Timer:</label></td>
                <td>
                    <input id="monitor_delay" name="monitor_delay" class="short" type="text" value="{$ftmux->monitor_delay}" />
                    <div class="hint">The time between query refreshes of monitor information, in seconds. This has no effect on any other pane, except in regards to the kill switches. The other panes are checked every 10 seconds. The lower the number, the more often it queries the database for numbers.<br />
                        <b>As the database gets larger in size, the longer this set of queries takes to process.</b><br />
                        this has been mitigated by using memcache on the count queries. The will stay in cache for whatever you have set in config.ini, default is 900 seconds.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="tmux_session">Tmux Session:</label></td>
                <td>
                    <input id="tmux_session" name="tmux_session" class="long" type="text" value="{$ftmux->tmux_session}" />
                    <div class="hint">Enter the session name to be used by tmux, no spaces allowed in the name, this can't be changed after scripts start. If you are running multiple servers, you could put your hostname here</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="monitor_path">Monitor a Ramdisk:</label></td>
                <td>
                    <input id="monitor_path" name="monitor_path" class="long" type="text" value="{$ftmux->monitor_path}" /><br />
                    <input id="monitor_path_a" name="monitor_path_a" class="long" type="text" value="{$ftmux->monitor_path_a}" /><br />
                    <input id="monitor_path_b" name="monitor_path_b" class="long" type="text" value="{$ftmux->monitor_path_b}" />
                    <div class="hint">Enter a path here to have Monitor monitor its usage and free space. Must be a valid path.<br />To use this example, add to fstab and edit path, gid and uid, then mount as user not root:<br />tmpfs /var/www/newznab/nzbfiles/tmpunrar tmpfs user,uid=1000,gid=33,nodev,nodiratime,nosuid,size=1G,mode=777 0 0<br />
                        gid == group id == /etc/groups, uid == user id == /etc/passwd</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">
                        Monitor is the name of the script that monitors all of the tmux panes and windows. It stops/stops scripts based on user settings. It queries the database to provide stats from your newznab database.<br /><br />
                        There are 2 columns of numbers, 'In Process' and 'In Database'. The 'In Process' is all releases that need to be postprocessed. The 'In Database' is the number of releases matching that category.<br /><br />
                        The 'In Process' column has 2 sets of numbers, the total for each category that needs to be postprocessed and inside the parenthesis is the difference from when the script started to what it is now.<br /><br />
                        The 'In Database' column also has 2 sets of numbers, the total releases for each category and inside the parenthesis is the percentage that category is to the total number of releases.<br /><br />
                        The Misc row means something different in both columns. The 'In Process' column is all releases that have not had 'Additional' run on them. This includes 100% of all releases, not just the Misc Category.<br /><br />
                        The 'In Database' Misc means the number of releases that have not been categorized in any other category.<br />
                        The counts for parts, binaries and predb totals are estimates and can vary wildly between queries. It is too slow to query the db for real counts, when using InnoDB. All of the other counts are actual counts.<br /><br />
                        The 'In Process' predb is the total unmatched predb and inside the parenthesis is the 'matched' changed since the script started. The 'In Database' is the total matched predb's you have and the number inside the parenthesis is the percentage of total releases that you have matched to a predb release.<br /><br />
                        The 'In Process' NZBs are total nzbs, inside the parenthesis is distinct nzbs and 'In Database' are nzbs that have all parts available and will be processed on next run.<br /><br />
                        The 'In Process' requestID is the number waiting to be processed and inside the parenthesis is the number changed since the script started. The 'In Database' is the total matches of releases to requestIDs and inside the parenthesis is percentage of total releases that you have matched to a requestID.<br /><br />
                        The 'In Process' rows PC and Pron are simply subsets of the 'In Process' row Misc. There is no postprocessing specifically for these categories. The 'In Database' is the actual count for the category.
                    </div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Sequential</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="sequential">Run Sequential:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="sequential" name='sequential' values=$sequential_ids output=$sequential_names selected=$ftmux->sequential}
                    <div class="hint">Basic Sequential runs update_binaries, backfill and update releases_sequentially.<br />Changing requires restart.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="seq_timer">Sequential Sleep Timer:</label></td>
                <td>
                    <input id="seq_timer" name="seq_timer" class="short" type="text" value="{$ftmux->seq_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">I recommend using Sequential, if you are also grabbing nzbs.</div>
                </td>
            </tr>

        </table>
    </fieldset>

    <fieldset>
        <legend>Update Binaries</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="binaries">Update Binaries:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="binaries" name='binaries' values=$binaries_ids output=$binaries_names selected=$ftmux->binaries}
                    <div class="hint">Choose to run update_binaries. Update binaries gets from your last_record to now.<br />Simple Threaded Update runs 1 group per thread.<br />Complete Threaded Update splits all work across multiple threads(binaries_safe_threaded.py).</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="bins_timer">Update Binaries Sleep Timer:</label></td>
                <td>
                    <input id="bins_timer" name="bins_timer" class="short" type="text" value="{$ftmux->bins_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Backfill</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="backfill">Backfill:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="backfill" name='backfill' values=$backfill_ids output=$backfill_names selected=$ftmux->backfill}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{html_options style="width:180px;" class="siteeditstyle" id="backfill_order" name='backfill_order' values=$backfill_group_ids output=$backfill_group selected=$ftmux->backfill_order}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{html_options style="width:180px;" class="siteeditstyle" id="backfill_days" name='backfill_days' values=$backfill_days_ids output=$backfill_days selected=$ftmux->backfill_days}
                    <div class="hint">Choose to run backfill type. Backfill gets from your first_record back.<br />
                        Disabled - Disables backfill from running.<br />
                        Safe - Backfills 1 group by backfill days (set in admin-view groups), using the number of threads set in admin. This downloads Backfill Quantity times the Backfill Threads, each loop(backfill_safe_threaded.py). <br \>
                        example: you have Backfill Threads = 10, Backfill Quantity = 20k, Max Messages = 5k: you will run 10 threads, queue of 40 and download 200k headers.<br />
                        Interval - Backfills the number of groups (set in tmux), by backfill days (set in admin-view groups), completely.<br />
                        All - Backfills the number of groups (set in tmux), by Backfill Quantity (set in tmux), up to backfill days (set in admin-view groups)<br />
                        These settings are all per loop and does not use backfill date. Approximately every 80 minutes, every activated backfill group will be backfilled (5k headers).This extra step is not necessary and is not used when using Sequential.<br />
                        Newest - Sorts the group selection with the least backfill days backfilled, first.<br />
                        Oldest - Sorts the group selection with the most backfill days backfilled, first.<br />
                        Alphabetical - Sorts the group selection from a to z.<br />
                        Alphabetical Reverse - Sorts the group selection from z to a.<br /a>
                            Most Posts - Sorts the group selection by the highest number of posts, first.<br /a>
                            Fewest Posts - Sorts the group selection by the lowest number of posts, first.<br />
                        Backfill days - Days per Group from admin->view group or the Safe Backfill Date from admin->tmux.</div>
                </td>
            </tr>
            <tr>
                <td style="width:180px;"><label for="backfill_qty">Backfill Quantity:</label></td>
                <td>
                    <input id="backfill_qty" name="backfill_qty" class="short" type="text" value="{$ftmux->backfill_qty}" />
                    <div class="hint">When not running backfill intervals, you select the number of headers per group per thread to download.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="backfill_groups">Backfill Groups:</label></td>
                <td>
                    <input id="backfill_groups" name="backfill_groups" class="short" type="text" value="{$ftmux->backfill_groups}" />
                    <div class="hint">When running backfill the groups are sorted by the backfill method chosen above. Select the number of groups to backfill per loop.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="back_timer">Backfill Sleep Timer:</label></td>
                <td>
                    <input id="back_timer" name="back_timer" class="short" type="text" value="{$ftmux->back_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="progressive">Variable Sleep Timer:</label></td>
                <td>
                    {html_radios id="progressive" name='progressive' values=$yesno_ids output=$yesno_names selected=$ftmux->progressive separator='<br />'}
                    <div class="hint">This will vary the backfill sleep depending on how many collections you have.<br />ie 50k collections would make sleep timer 100 seconds and 20k releases would make sleep timer 40 seconds.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Import nzbs</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="import">Import nzbs:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="import" name='import' values=$import_ids output=$import_names selected=$ftmux->import}
                    <div class="hint">Choose to run import nzb script true/false. This can point to a single folder with multiple subfolders on just the one folder. If you run this threaded, it will run 1 folder per thread.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="nzbs">Nzbs:</label></td>
                <td>
                    <input id="nzbs" class="long" name="nzbs" type="text" value="{$ftmux->nzbs}" />
                    <div class="hint">Set the path to the nzb dump you downloaded from torrents, this is the path to bulk files folder of nzbs. This is by default, recursive and threaded. You set the threads in Advanced Settings.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="import_timer">Import nzbs Sleep Timer:</label></td>
                <td>
                    <input id="import_timer" name="import_timer" class="short" type="text" value="{$ftmux->import_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">This will import all nzbs in the given path. If in your path you have nzbs in the root folder and subfolders(regardless of nzbs inside), threaded scripts will ignore all nzbs in the root path. Then each subfolder is threaded.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Update Releases</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="releases">Update Releases:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="releases" name='releases' values=$releases_ids output=$releases_names selected=$ftmux->releases}
                    <div class="hint">Create releases, this is really only necessary to turn off when you only want to post process. This runs "Categorizes releases in misc sections using the search name" on first loop and has 33% chance of running on any other loop. Then runs update_releases.php 1 false to create new releases.<br />Threaded is only used with tablepergroup and is required if using tpg.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="rel_timer">Update Releases Sleep Timer:</label></td>
                <td>
                    <input id="rel_timer" name="rel_timer" class="short" type="text" value="{$ftmux->rel_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Postprocessing</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="post">Postprocess Additional:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="post" name='post' values=$post_ids output=$post_names selected=$ftmux->post}
                    <div class="hint">Choose to do deep rar inspection, preview and sample creation and/or nfo processing. true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="post_timer">Postprocess Additional Sleep Timer:</label></td>
                <td>
                    <input id="post_timer" name="post_timer" class="short" type="text" value="{$ftmux->post_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="post_kill_timer">Postprocess Kill Timer:</label></td>
                <td>
                    <input id="post_kill_timer" name="post_kill_timer" class="short" type="text" value="{$ftmux->post_kill_timer}" />
                    <div class="hint">The time postprocess is allowed to run with no updates to the screen.Time is in minutes. Activity is detected when the history for the pane changes. The clock is restarted every time activity is detected.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="post_amazon">Postprocess Amazon:</label></td>
                <td>
                    {html_radios id="post_amazon" name='post_amazon' values=$yesno_ids output=$yesno_names selected=$ftmux->post_amazon separator='<br />'}
                    <div class="hint">Choose to do books, music and games lookups true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="post_timer_amazon">Postprocess Amazon Sleep Timer:</label></td>
                <td>
                    <input id="post_timer_amazon" name="post_timer_amazon" class="short" type="text" value="{$ftmux->post_timer_amazon}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="post_non">Postprocess Non-Amazon:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="post_non" name='post_non' values=$post_non_ids output=$post_non_names selected=$ftmux->post_non}
                    <div class="hint">Choose to do movies, anime and tv lookups. true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="post_timer">Postprocess Non-Amazon Sleep Timer:</label></td>
                <td>
                    <input id="post_timer_non" name="post_timer_non" class="short" type="text" value="{$ftmux->post_timer_non}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Fix Release Names</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="fix_names">Fix Release Names:</label></td>
                <td>
                    {html_radios id="fix_names" name='fix_names' values=$yesno_ids output=$yesno_names selected=$ftmux->fix_names separator='<br />'}
                    <div class="hint">Choose to try to fix Releases Names using NFOs, par2 files, filenames, md5 and misc sorter. true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="fix_timer">Fix Release Names Sleep Timer:</label></td>
                <td>
                    <input id="fix_timer" name="fix_timer" class="short" type="text" value="{$ftmux->fix_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Remove Crap Releases</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="fix_crap">Remove Crap Releases:</label></td>
                <td>
                    {html_radios id="fix_crap_opt" name='fix_crap_opt' onchange="enableFixCrapCustom()" values=$fix_crap_radio_ids output=$fix_crap_radio_names selected=$ftmux->fix_crap_opt separator='<br />'}
                    <br>
                    <div class="checkbox-grid">
                        {if $ftmux->fix_crap_opt == "Custom"}
                            {html_checkboxes id="fix_crap" name='fix_crap' values=$fix_crap_check_ids output=$fix_crap_check_names selected=explode(', ', $ftmux->fix_crap)}
                        {else}
                            {html_checkboxes id="fix_crap" name='fix_crap' disabled="true" readonly="true" values=$fix_crap_check_ids output=$fix_crap_check_names selected=explode(', ', $ftmux->fix_crap)}
                        {/if}
                    </div>
                    <div class="hint">Choose to run Remove Crap Releases. You can all or some.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="crap_timer">Remove Crap Releases Sleep Timer:</label></td>
                <td>
                    <input id="crap_timer" name="crap_timer" class="short" type="text" value="{$ftmux->crap_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Decrypt Hashes</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="dehash">Decrypt Hash Based Release Names:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="dehash" name='dehash' values=$dehash_ids output=$dehash_names selected=$ftmux->dehash}
                    <div class="hint">Choose to run Decrypt Hashes true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="dehash_timer">Decrypt Hashes Sleep Timer:</label></td>
                <td>
                    <input id="dehash_timer" name="dehash_timer" class="short" type="text" value="{$ftmux->dehash_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">Decrypt hashes works by matching a hashed release to the md5 of a release in the predb.<br />
                        Included in the same pane is Update Predb. This scrapes several predb sites and then tries to match against releases.<br />
                        This should be run along with fixReleasenames.php, this is faster, but only looks at releases.names. fixReleasenames.php goes further and looks at releasefiles.name.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Update Spotnab</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="spotnab">Update comments from Spotnab:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="spotnab" name='spotnab' values=$spotnab_ids output=$spotnab_names selected=$ftmux->spotnab}
                    <div class="hint">Choose to update Spotnab true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="spotnab_timer">Spotnab Sleep Timer:</label></td>
                <td>
                    <input id="spotnab_timer" name="spotnab_timer" class="short" type="text" value="{$ftmux->spotnab_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">Spotnab updates comments from newznab sites that enabled this feature.<br />
                        You can set the privacy in admin->edit site.<br />
                        </div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Fetch Missing Movie Info</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="fetch_movie">Fetch missing movie info from imdb:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="fetch_movie" name='fetch_movie' values=$fetch_movie_ids output=$fetch_movie_names selected=$ftmux->fetch_movie}
                    <div class="hint">Choose to update missing movie info from imdb true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="movie_timer">Fetch Movie Sleep Timer:</label></td>
                <td>
                    <input id="movie_timer" name="movie_timer" class="short" type="text" value="{$ftmux->movie_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">Fetch missing movie info will fetch data from imdb that is missing from movie releases.<br /></div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Update PreDB</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="predb">Update PreDB:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="predb" name='predb' values=$predb_ids output=$predb_names selected=$ftmux->predb}
                    <div class="hint">Choose to update PreDb from nzpre true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="predb_timer">PreDB Sleep Timer:</label></td>
                <td>
                    <input id="predb_timer" name="predb_timer" class="short" type="text" value="{$ftmux->predb_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">Update PreDB data from nzpre. Enable only if you have nzpre access.<br />
                        You need to enter nzpre details in admin->edit site, otherwise this wont do anything.<br /></div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Unwanted releases</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="dehash">Postprocess unwanted releases:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="unwanted" name='unwanted' values=$unwanted_ids output=$unwanted_names selected=$ftmux->unwanted}
                    <div class="hint">Choose to postprocess unwanted releases true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">This setting does postprocess unwanted releases.<br /></div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Postprocess Others</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="dehash">Postprocess other releases:</label></td>
                <td>
                    {html_options style="width:180px;" class="siteeditstyle" id="others" name='others' values=$others_ids output=$others_names selected=$ftmux->others}
                    <div class="hint">Choose to postprocess other (misc other) releases true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">Postprocess others tries to find a valid release in other->misc category.<br /></div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Sphinx</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="fix_names">Run Sphinx:</label></td>
                <td>
                    {html_radios id="sphinx" name='sphinx' values=$yesno_ids output=$yesno_names selected=$ftmux->sphinx separator='<br />'}
                    <div class="hint">Choose to run Sphinxsearch</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="sphinx_timer">Sphinx Sleep Timer:</label></td>
                <td>
                    <input id="sphinx_timer" name="sphinx_timer" class="short" type="text" value="{$ftmux->sphinx_timer}" />
                    <div class="hint">The time to sleep from the time the loop ends until it is restarted, in seconds.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Update TV/Theater</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="update_tv">Update TV and Theater Schedules:</label></td>
                <td>
                    {html_radios id="update_tv" name='update_tv' values=$yesno_ids output=$yesno_names selected=$ftmux->update_tv separator='<br />'}
                    <div class="hint">Choose to run Update TV and Theater Schedules true/false</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="tv_timer">Update TV and Theater Start Timer:</label></td>
                <td>
                    <input id="tv_timer" name="tv_timer" class="short" type="text" value="{$ftmux->tv_timer}" />
                    <div class="hint">This is a start timer. The default is 12 hours. This means that if enabled, is will start/run every 12 hours, no matter how long it runs for.</div>
                </td>
            </tr>
        </table>
    </fieldset>

     <fieldset>
        <legend>PreDb IRC Scraper</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="scrape_cz">Scrape Corrupt/Zenet Irc Channels:</label></td>
                <td>
                    {html_radios id="scrape_cz" name='scrape_cz' values=$yesno_ids output=$yesno_names
                    selected=$ftmux->scrape_cz separator='<br />'} <div class="hint">Choose to run IRCScraper on corrupt/zenet irc channels. Setting SCRAPE_IRC_C_Z_BOOL parameter to true or false in settings.php will toggle between the servers. The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="scrape_efnet">Scrape Efnet Irc Channels:</label></td>
                <td>
                    {html_radios id="scrape_efnet" name='scrape_efnet' values=$yesno_ids output=$yesno_names selected=$ftmux->scrape_efnet separator='<br />'} <div class="hint">Choose to run IRCScraper on Efnet irc channels. The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>
            <tr>
                <td style="width:180px;"><label for="explain">Information:</label></td>
                <td>
                    <div class="explanation">IRC Scraper will scrape several predb channels from the enabled servers.<br />
                        Copy settings_example.php to settings.php in /lib/IRCScraper and change the settings.<br />
                        As a minimum you should set the username and make sure it is unique.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Miscellaneous</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="niceness">Niceness:</label></td>
                <td>
                    <input id="niceness" name="niceness" class="short" type="text" value="{$ftmux->niceness}" />
                    <div class="hint">This sets the 'nice'ness of each script, default is 19, the lowest, the highest is -20 anything between -1 and -20 require root/sudo to run</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="postprocess_kill">Maximum Postprocess:</label></td>
                <td>
                    <input id="postprocess_kill" name="postprocess_kill" class="short" type="text" value="{$ftmux->postprocess_kill}" />
                    <div class="hint">Set this to any number above 0 and when it is exceeded, import, backfill and update binaries will be terminated. 0 disables.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="write_logs">Logging:</label></td>
                <td>
                    {html_radios id="write_logs" name='write_logs' values=$yesno_ids output=$yesno_names selected=$ftmux->write_logs separator='<br />'}
                    <div class="hint">Set this to write each panes output to a per pane per day log file. This adds GMT date to the filename.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="powerline">Powerline Status Bar:</label></td>
                <td>
                    {html_radios id="powerline" name='powerline' values=$yesno_ids output=$yesno_names selected=$ftmux->powerline separator='<br />'}
                    <div class="hint">Choose to use the Powerline tmux status bar. To make this pretty, you need to install a patched font. This can be found on <a href="https://github.com/jonnyboy/powerline-fonts">my fork</a> or <a href="https://github.com/Lokaltog/powerline-fonts">the original git</a><br \>You will need to copy the default theme located at powerline/powerline/themes/default.sh to powerline/powerline/themes/tmux.sh and edit that file for what is displayed, colors, etc.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="max_load">Maximum Load:</label></td>
                <td>
                    <input id="max_load" name="max_load" class="short" type="text" value="{$ftmux->max_load}" />
                    <div class="hint">Set this to limit the load on your system to value entered. Default value is 2.0.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="max_load_releases">Maximum Releases Load:</label></td>
                <td>
                    <input id="max_load_releases" name="max_load_releases" class="short" type="text" value="{$ftmux->max_load_releases}" />
                    <div class="hint">Set this to limit the load on your system to value entered. Default value is 2.0.</div>
                </td>
            </tr>

            <tr>
				<td style="width:180px;"><label for="safebackfilldate">Safe Backfill Date:</label></td>
				<td>
					<input class="small" id="safebackfilldate" name="safebackfilldate" type="text" value="{$ftmux->safebackfilldate}" />
					<div class="hint">The target date for safe backfill. Format: YYYY-MM-DD</div>
				</td>
			</tr>

            <tr>
				<td style="width:180px;"><label for="safepartrepair">Part Repair for Backfill Scripts:</label></td>
				<td>
					{html_radios id="safepartrepair" name='safepartrepair' values=$yesno_ids output=$yesno_names selected=$ftmux->safepartrepair separator='<br />'}
					<div class="hint">Whether to put unreceived parts into partrepair table when running binaries(safe) or backfill scripts.</div>
				</td>
			</tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Server Monitors</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="showquery">Display Query Times:</label></td>
                <td>
                    {html_radios id="showquery" name='showquery' values=$yesno_ids output=$yesno_names selected=$ftmux->showquery separator='<br />'}
                    <div class="hint">Choose to display the query times for each set of queries. true/false.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="htop">htop:</label></td>
                <td>
                    {html_radios id="htop" name='htop' values=$yesno_ids output=$yesno_names selected=$ftmux->htop separator='<br />'}
                    <div class="hint">htop - an interactive process viewer for Linux. The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="nmon">nmon:</label></td>
                <td>
                    {html_radios id="nmon" name='nmon' values=$yesno_ids output=$yesno_names selected=$ftmux->nmon separator='<br />'}
                    <div class="hint">nmon is short for Nigel's performance Monitor for Linux. The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="vnstat">vnstat:</label></td>
                <td>
                    {html_radios id="vnstat" name='vnstat' values=$yesno_ids output=$yesno_names selected=$ftmux->vnstat separator='<br />'}
                    <input id="vnstat_ARGS" name="vnstat_ARGS" class="text" type="text" value="{$ftmux->vnstat_ARGS}" />
                    <div class="hint">vnStat is a console-based network traffic monitor for Linux and BSD that keeps a log of network traffic for the selected interface(s). Any additional arguments should be placed in the text box. The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="tcptrack">tcptrack:</label></td>
                <td>
                    {html_radios id="tcptrack" name='tcptrack' values=$yesno_ids output=$yesno_names selected=$ftmux->tcptrack separator='<br />'}
                    <input id="tcptrack_args" name="tcptrack_args" class="text" type="text" value="{$ftmux->tcptrack_args}" />
                    <div class="hint">tcptrack displays the status of TCP connections that it sees on a given network interface. tcptrack monitors their state and displays information such as state, source/destination addresses and bandwidth usage in a sorted, updated list very much like the top(1) command. <br />Any additional arguments should be placed in the text box. <br />You may need to run "sudo setcap cap_net_raw+ep /usr/bin/tcptrack", to be able to run as user. The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="bwmng">bwm-ng:</label></td>
                <td>
                    {html_radios id="bwmng" name='bwmng' values=$yesno_ids output=$yesno_names selected=$ftmux->bwmng separator='<br />'}
                    <div class="hint">bwm-ng can be used to monitor the current bandwidth of all or some specific network interfaces or disks (or partitions). The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="mytop">mytop:</label></td>
                <td>
                    {html_radios id="mytop" name='mytop' values=$yesno_ids output=$yesno_names selected=$ftmux->mytop separator='<br />'}
                    <div class="hint">mytop - display MySQL server performance info like `top'. <br />You will need to create ~/.mytop, an example can be found in 'perldoc mytop'. The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="showprocesslist">Show Process List:</label></td>
                <td>
                    {html_radios id="showprocesslist" name='showprocesslist' values=$yesno_ids output=$yesno_names selected=$ftmux->showprocesslist separator='<br />'}
                    <input id="processupdate" name="processupdate" class="short" type="text" value="{$ftmux->processupdate}" />
                    <div class="hint">This runs the watch command in its own pane. This could be used for a live 'slow query monitor'. Just set the time above and only the queries that exceed it will be displayed.<br />mysql -e "SELECT time, state, rows_examined, info FROM information_schema.processlist WHERE command != \"Sleep\" AND time >= .5 ORDER BY time DESC \G"<br />This shows a grid like layout with the full test of the running queries.<br />You will need to create a ~/.my.cnf for this to work properlly. The pane for this can not be created after tmux starts and modifying the time above will have no effect until a restart occurs.</div>
                </td>
            </tr>

            <tr>
                <td style="width:180px;"><label for="console">Console:</label></td>
                <td>
                    {html_radios id="console" name='console' values=$yesno_ids output=$yesno_names selected=$ftmux->console separator='<br />'}
                    <div class="hint">Open an empty bash shell. The pane for this can not be created after tmux starts.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
        <legend>Tmux Colors</legend>
        <table class="input">
            <tr>
                <td style="width:180px;"><label for="colors">256 Colors:</label></td>
                <td>
                    {html_radios id="colors" name='colors' values=$yesno_ids output=$yesno_names selected=$ftmux->colors separator='<br />'}
                    <div class="hint">Show a color display of all available colors based on the settings below. true/fasle</div>
                    <input id="colors_start" name="colors_start" class="short" type="text" value="{$ftmux->colors_start}" />
                    <input id="colors_end" name="colors_end" class="short" type="text" value="{$ftmux->colors_end}" /><br />
                    <input id="colors_exc" name="colors_exc" class="longer" type="text" value="{$ftmux->colors_exc}" />
                    <div class="hint">The color displayed is tmux scripts is randomized from this list.<br />
                        The first box is the start number, the second box is the end number and the last box are the exceptions. An array is created from these numbers.<br \>The exceptions do not need to be in numerical order.<br />The start number must be smaller than the end number.<br />
                        If you connect using putty, then under Window/Translation set Remote character set to UTF-8 and check "Copy and paste line drawing characters". To use 256 colors, you must set Connection/Data Terminal-type string to "xterm-256color" and in Window/Colours check the top three boxes, otherwise only 16 colors are displayed. If you are using FreeBSD, you will need to add export TERM=xterm-256color to your .bashrc file to show 256 colors.</div>
                </td>
            </tr>
        </table>
    </fieldset>

    <fieldset>
		<legend>TraktTV API Key</legend>
		<table class="input">
            <tr>
				<td style="width:180px;"><label for="trakttvkey">Trakt.tv API key:</label></td>
				<td>
					<input id="trakttvkey" class="long" name="trakttvkey" type="text" value="{$ftmux->trakttvkey}" />
					<div class="hint">The trakt.tv api key. Used for movie and tv lookups.</div>
				</td>
			</tr>
        	</table>
	</fieldset>

	<fieldset>
		<legend>Fanart.tv API Key</legend>
		<table class="input">
            <tr>
				<td style="width:180px;"><label for="fanarttvkey">Fanart.tv API key:</label></td>
				<td>
					<input id="fanarttvkey" class="long" name="fanarttvkey" type="text" value="{$ftmux->fanarttvkey}" />
					<div class="hint">The Fanart.tv api key. Used for Fanart.tv lookups. Fanart.tv would appreciate it if you use this service to help them out by adding high quality images not already available on TMDB.</div>
				</td>
			</tr>
        	</table>
	</fieldset>

	<fieldset>
		<legend>IMDB.com URL</legend>
		<table class="input">
		<tr>
				<td style="width:180px;"><label for="imdburl">IMDB.com:</label></td>
				<td>
					{html_options style="width:180px;" class="imdburl" id="imdburl" name='imdburl' values=$imdb_urls output=$imdburl_names selected=$ftmux->imdburl}
					<div class="hint">Akas.imdb.com returns titles in their original title, imdb.com returns titles based on your IP address (if you are in france, you will get french titles).</div>
				</td>
			</tr>
			</table>
	</fieldset>

    <fieldset>
		<legend>Usenet Settings</legend>
		<table class="input">

			<tr>
				<td style="width:180px;"><label for="maxsizetopostprocess">Maximum File Size to Postprocess:</label></td>
				<td>
					<input class="short" id="maxsizetopostprocess" name="maxsizetopostprocess" type="text" value="{$ftmux->maxsizetopostprocess}" />
					<div class="hint">The maximum size in gigabytes to postprocess a release. If set to 0, then ignored.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="segmentstodownload">Number of Segments to download for video samples:</label></td>
				<td>
					<input class="short" id="segmentstodownload" name="segmentstodownload" type="text" value="{$ftmux->segmentstodownload}" />
					<div class="hint">The maximum number of segments to download to generate the sample video file. (Default 2)</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="lookuppar2">Lookup PAR2:</label></td>
				<td>
					{html_radios id="lookuppar2" name='lookuppar2' values=$yesno_ids output=$yesno_names selected=$ftmux->lookuppar2 separator='<br />'}
					<div class="hint">Whether to attempt to find a better name for releases in misc->other using the PAR2 file.<br/><strong>NOTE: this can be slow depending on the group!</strong></div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="addpar2">Add PAR2 contents to file contents:</label></td>
				<td>
					{html_radios id="addpar2" name='addpar2' values=$yesno_ids output=$yesno_names selected=$ftmux->addpar2 separator='<br />'}
					<div class="hint">When going through PAR2 files, add them to the RAR file content list of the NZB.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="lookup_reqids">Lookup Request IDs:</label></td>
				<td>
					{html_options style="width:180px;" id="lookup_reqids" name='lookup_reqids' values=$lookup_reqids_ids output=$lookup_reqids_names selected=$ftmux->lookup_reqids}
					<div class="hint">Whether to attempt to lookup Request IDs using the Request ID link below.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="style">Request ID Link:</label></td>
				<td>
					<input id="request_url" class="long" name="request_url" type="text" value="{$ftmux->request_url}" />
					<div class="hint">Optional URL to lookup Request IDs.  [REQUEST_ID] gets replaced with the request ID from the post.  [GROUP_NM] Gets replaced with the group name.</div>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset>
		<legend>Advanced Settings - For advanced users</legend>
		<table class="input">

			<tr>
				<td style="width:180px;"><label for="maxmssgs">Max Messages:</label></td>
				<td>
					<input class="short" id="maxmssgs" name="maxmssgs" type="text" value="{$ftmux->maxmssgs}" />
					<div class="hint">The maximum number of messages to fetch at a time from the server. Only raise this if you have php set right and lots of RAM.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxnzbsprocessed">Maximum NZBs processed:</label></td>
				<td>
					<input class="short" id="maxnzbsprocessed" name="maxnzbsprocessed" type="text" value="{$ftmux->maxnzbsprocessed}" />
					<div class="hint">The maximum amount of NZB files to create in update_releases.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxpartrepair">Maximum repair per run:</label></td>
				<td>
					<input class="short" id="maxpartrepair" name="maxpartrepair" type="text" value="{$ftmux->maxpartrepair}" />
					<div class="hint">The maximum amount of articles to attempt to repair at a time. If you notice that you are getting a lot of parts into the partrepair table, it is possible that you USP is not keeping up with the requests. Try to reduce the threads to safe scripts, stop using safe scripts or stop using nntpproxy until improves. Ar least until the cause can be determined.</div>
				</td>
			</tr>

            <tr>
				<td style="width:180px;"><label for="partrepair">Part Repair:</label></td>
				<td>
					{html_radios id="partrepair" name='partrepair' values=$yesno_ids output=$yesno_names selected=$ftmux->partrepair separator='<br />'}
					<div class="hint">Whether to attempt to repair parts or not, increases backfill/binaries updating time.</div>
				</td>
			</tr>

            <tr>
				<td style="width:180px;"><label for="zippath">7za Path:</label></td>
				<td>
					<input id="zippath" class="long" name="zippath" type="text" value="{$ftmux->zippath}" />
					<div class="hint">The path to the 7za/p7zip in Ubuntu 13.10 (7zip command line in windows) binary, used for grabbing nfos from compressed zip files.
						<br/>Use forward slashes in windows <span style="font-family:courier;">c:/path/to/7z.exe</span></div>
				</td>
			</tr>

            <tr>
				<td style="width:180px;"><label for="processjpg">Process JPG:</label></td>
				<td>
					{html_radios id="processjpg" name='processjpg' values=$yesno_ids output=$yesno_names selected=$ftmux->processjpg separator='<br />'}
					<div class="hint">Whether to attempt to retrieve a JPG file while additional post processing, these are usually on XXX releases.<br/></div>
				</td>
			</tr>

            <tr>
				<td style="width:180px;"><label for="sharing_timer">Comments Sharing Timer:</label></td>
				<td>
					<input class="short" id="sharing_timer" name="sharing_timer" type="text" value="{$ftmux->sharing_timer}" />
					<div class="hint">Set the timer for updating comments shared between newznab and nZEDb servers</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="debuginfo">Debug information:</label></td>
				<td>
					{html_radios id="debuginfo" name='debuginfo' values=$yesno_ids output=$yesno_names selected=$ftmux->debuginfo separator='<br />'}
					<div class="hint">For developers. Whether to echo debug information in some scripts.</div>
				</td>
			</tr>
		</table>
	</fieldset>

	<fieldset>
		<legend>Advanced - Postprocessing Settings</legend>
		<table class="input">
			<tr>
				<td style="width:180px;"><label for="maxaddprocessed">Maximum add PP per run:</label></td>
				<td>
					<input class="short" id="maxaddprocessed" name="maxaddprocessed" type="text" value="{$ftmux->maxaddprocessed}" />
					<div class="hint">The maximum amount of releases to process for passwords/previews/mediainfo per run. Every release gets processed here. This uses NNTP an connection, 1 per thread. This does not query Amazon.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxpartsprocessed">Maximum add PP parts downloaded:</label></td>
				<td>
					<input class="short" id="maxpartsprocessed" name="maxpartsprocessed" type="text" value="{$ftmux->maxpartsprocessed}" />
					<div class="hint">If a part fails to download while post processing, this will retry up to the amount you set, then give up.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="passchkattempts">Maximum add PP parts checked:</label></td>
				<td>
					<input class="short" id="passchkattempts" name="passchkattempts" type="text" value="{$ftmux->passchkattempts}" />
					<div class="hint">This overrides the above setting if set above 1. How many parts to check for a password before giving up. This slows down post processing massively, better to leave it 1.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxnfoprocessed">Maximum NFO files per run:</label></td>
				<td>
					<input class="short" id="maxnfoprocessed" name="maxnfoprocessed" type="text" value="{$ftmux->maxnfoprocessed}" />
					<div class="hint">The maximum amount of NFO files to process per run. This uses NNTP an connection, 1 per thread. This does not query Amazon.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxrageprocessed">Maximum TVRage per run:</label></td>
				<td>
					<input class="short" id="maxrageprocessed" name="maxrageprocessed" type="text" value="{$ftmux->maxrageprocessed}" />
					<div class="hint">The maximum amount of TV shows to process with TVRage per run. This does not use an NNTP connection or query Amazon.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maximdbprocessed">Maximum movies per run:</label></td>
				<td>
					<input class="short" id="maximdbprocessed" name="maximdbprocessed" type="text" value="{$ftmux->maximdbprocessed}" />
					<div class="hint">The maximum amount of movies to process with IMDB per run. This does not use an NNTP connection or query Amazon.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxanidbprocessed">Maximum anidb per run:</label></td>
				<td>
					<input class="short" id="maxanidbprocessed" name="maxanidbprocessed" type="text" value="{$ftmux->maxanidbprocessed}" />
					<div class="hint">The maximum amount of anime to process with anidb per run. This does not use an NNTP connection or query Amazon.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxmusicprocessed">Maximum music per run:</label></td>
				<td>
					<input class="short" id="maxmusicprocessed" name="maxmusicprocessed" type="text" value="{$ftmux->maxmusicprocessed}" />
					<div class="hint">The maximum amount of music to process with amazon per run. This does not use an NNTP connection.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxgamesprocessed">Maximum games per run:</label></td>
				<td>
					<input class="short" id="maxgamesprocessed" name="maxgamesprocessed" type="text" value="{$ftmux->maxgamesprocessed}" />
					<div class="hint">The maximum amount of games to process with amazon per run. This does not use an NNTP connection.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="maxbooksprocessed">Maximum books per run:</label></td>
				<td>
					<input class="short" id="maxbooksprocessed" name="maxbooksprocessed" type="text" value="{$ftmux->maxbooksprocessed}" />
					<div class="hint">The maximum amount of books to process with amazon per run. This does not use an NNTP connection</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="fixnamesperrun">fixReleaseNames per Run:</label></td>
				<td>
					<input class="short" id="fixnamesperrun" name="fixnamesperrun" type="text" value="{$ftmux->fixnamesperrun}" />
					<div class="hint">The maximum number of releases to check per run(threaded script only).</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="amazonsleep">Amazon sleep time:</label></td>
				<td>
					<input class="short" id="amazonsleep" name="amazonsleep" type="text" value="{$ftmux->amazonsleep}" />
					<div class="hint">Sleep time in milliseconds to wait in between amazon requests. If you thread post-proc, multiply by the number of threads. ie Postprocessing Threads = 12, Amazon sleep time = 12000<br /><a href="https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html">https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html</a></div>
				</td>
			</tr>

		</table>
	</fieldset>

    <fieldset>
		<legend>Connection Settings</legend>
		<table class="input">

			<tr>
				<td style="width:180px;"><label for="nntpretries">NNTP Retry Attempts:</label></td>
				<td>
					<input class="short" id="nntpretries" name="nntpretries" type="text" value="{$ftmux->nntpretries}" />
					<div class="hint">The maximum number of retry attmpts to connect to nntp provider. On error, each retry takes approximately 5 seconds nntp returns reply. (Default 10)</div>
				</td>
			</tr>
		</table>
	</fieldset>

    <fieldset>
		<legend>Advanced - Threaded Settings</legend>
		<table class="input">
			<tr>
				<td style="width:180px;"><label for="binarythreads">Update Binaries Threads:</label></td>
				<td>
					<input class="short" id="binarythreads" name="binarythreads" type="text" value="{$ftmux->binarythreads}" />
					<div class="hint">The number of threads for update_binaries. If you notice that you are getting a lot of parts into the partrepair table, it is possible that you USP is not keeping up with the requests. Try to reduce the threads. At least until the cause can be determined.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="backfillthreads">Backfill Threads:</label></td>
				<td>
					<input class="short" id="backfillthreads" name="backfillthreads" type="text" value="{$ftmux->backfillthreads}" />
					<div class="hint">The number of threads for backfill.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="releasesthreads">Update Releases Threads:</label></td>
				<td>
					<input class="short" id="releasethreads" name="releasethreads" type="text" value="{$ftmux->releasethreads}" />
					<div class="hint">The number of threads for update_releases. This actualy makes no difference, newznab update_releases is not threaded.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="nzbthreads">Import-nzb Threads:</label></td>
				<td>
					<input class="short" id="nzbthreads" name="nzbthreads" type="text" value="{$ftmux->nzbthreads}" />
					<div class="hint">The number of threads for import-nzb(bulk). This will thread each subfolder.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="postthreads">Postprocessing Additional Threads:</label></td>
				<td>
					<input class="short" id="postthreads" name="postthreads" type="text" value="{$ftmux->postthreads}" />
					<div class="hint">The number of threads for additional postprocessing. This includes deep rar inspection, preview and sample creation and nfo processing.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="postthreadsnon">Postprocessing Non-Amazon Threads:</label></td>
				<td>
					<input class="short" id="postthreadsnon" name="postthreadsnon" type="text" value="{$ftmux->postthreadsnon}" />
					<div class="hint">The number of threads for non-amazon postprocessing. This includes movies, anime and tv lookups.</div>
				</td>
			</tr>

			<tr>
				<td style="width:180px;"><label for="fixnamethreads">fixReleaseNames Threads:</label></td>
				<td>
					<input class="short" id="fixnamethreads"  name="fixnamethreads" type="text" value="{$ftmux->fixnamethreads}" />
					<div class="hint">The number of threads for fixReleasesNames. This includes md5, nfos, par2 and filenames.</div>
				</td>
			</tr>

		</table>
	</fieldset>

    <input type="submit" value="Save Tmux Settings" />

</form>

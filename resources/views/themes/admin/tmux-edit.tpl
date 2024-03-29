<div class="card card-body">
	<h1>{$title}</h1>

	<form action="tmux-edit?action=submit" method="post">
		{{csrf_field()}}

		{if isset($error) && $error != ''}
			<div class="error">{$error}</div>
		{/if}

		<fieldset>
			<legend>Tmux - How It Works</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="explain">Information:</label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					</td>
					<td>
						<div class="explanation">Tmux is a screen multiplexer and at least version 1.6 is required. It
							is used
							here to allow multiple windows per session and multiple panes per window.<br/><br/>
							Each script is run in its own shell environment. It is not looped, but allowed to run once
							and then
							exit. This notifies tmux that the pane is dead and can then be respawned with another
							iteration of
							the script in a new shell environment. <br/>
							This allows for scripts that crash to be restarted without user intervention.<br/><br/>
							You can run multiple tmux sessions, but they all must have an associated tmux.conf file and
							all
							sessions must use the same tmux.conf file.<br/><br/>

							<h3><b>NOTICE:</b></h3> If "Save Tmux Settings" is the last thing you did on this page or if
							it is
							the active element and if you have this page set to autorefresh or you refresh instead of
							following
							a link to this page, you will set the db with the settings currently on this page, not
							reload from
							db. This could cause tmux scripts to start while optimize or patch the database is running.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Monitor</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="running">Tmux Scripts Running:</label></td>
					<td>
						{html_radios id="running" name='running' values=$yesno_ids output=$yesno_names selected=$site->running separator='<br />'}
						<div class="hint">This is the shutdown, true/on, it runs, false/off and all scripts are
							terminated. This
							will start/stop all panes without terminating the monitor pane. This is not instant, it does
							not
							kill any panes, it simply does not allow any pane to restart if set to false.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="monitor_delay">Monitor Loop Timer:</label></td>
					<td>
						<input id="monitor_delay" name="monitor_delay" class="short" type="text"
							   value="{$site->monitor_delay}"/>

						<div class="hint">The time between query refreshes of monitor information, in seconds. This has
							no
							effect on any other pane, except in regards to the kill switches. The other panes are
							checked every
							10 seconds. The lower the number, the more often it queries the database for numbers.<br/>
							<b>As the database gets larger in size, the longer this set of queries takes to process.</b><br/>
							this has been mitigated by using memcache on the count queries. The will stay in cache for
							whatever
							you have set in config.ini, default is 900 seconds.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="tmux_session">Tmux Session:</label></td>
					<td>
						<input id="tmux_session" name="tmux_session" class="long" type="text"
							   value="{$site->tmux_session}"/>

						<div class="hint">Enter the session name to be used by tmux, no spaces allowed in the name, this
							can't
							be changed after scripts start. If you are running multiple servers, you could put your
							hostname
							here
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="monitor_path">Monitor a Ramdisk:</label></td>
					<td>
						<input id="monitor_path" name="monitor_path" class="long" type="text"
							   value="{$site->monitor_path}"/><br/>
						<input id="monitor_path_a" name="monitor_path_a" class="long" type="text"
							   value="{$site->monitor_path_a}"/><br/>
						<input id="monitor_path_b" name="monitor_path_b" class="long" type="text"
							   value="{$site->monitor_path_b}"/>

						<div class="hint">Enter a path here to have Monitor monitor its usage and free space. Must be a
							valid
							path.<br/>To use this example, add to fstab and edit path, gid and uid, then mount as user
							not root:<br/>tmpfs
							/var/www/newznab/nzbfiles/tmpunrar tmpfs
							user,uid=1000,gid=33,nodev,nodiratime,nosuid,size=1G,mode=777 0 0<br/>
							gid == group id == /etc/groups, uid == user id == /etc/passwd
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="explain">Information:</label></td>
					<td>
						<div class="explanation">
							Monitor is the name of the script that monitors all of the tmux panes and windows. It
							stops/stops
							scripts based on user settings. It queries the database to provide stats from your newznab
							database.<br/><br/>
							There are 2 columns of numbers, 'In Process' and 'In Database'. The 'In Process' is all
							releases
							that need to be postprocessed. The 'In Database' is the number of releases matching that
							category.<br/><br/>
							The 'In Process' column has 2 sets of numbers, the total for each category that needs to be
							postprocessed and inside the parenthesis is the difference from when the script started to
							what it
							is now.<br/><br/>
							The 'In Database' column also has 2 sets of numbers, the total releases for each category
							and inside
							the parenthesis is the percentage that category is to the total number of
							releases.<br/><br/>
							The Misc row means something different in both columns. The 'In Process' column is all
							releases that
							have not had 'Additional' run on them. This includes 100% of all releases, not just the Misc
							Category.<br/><br/>
							The 'In Database' Misc means the number of releases that have not been categorized in any
							other
							category.<br/>
							The counts for parts, binaries and predb totals are estimates and can vary wildly between
							queries.
							It is too slow to query the db for real counts, when using InnoDB. All of the other counts
							are
							actual counts.<br/><br/>
							The 'In Process' predb is the total unmatched predb and inside the parenthesis is the
							'matched'
							changed since the script started. The 'In Database' is the total matched predb's you have
							and the
							number inside the parenthesis is the percentage of total releases that you have matched to a
							predb
							release.<br/><br/>
							The 'In Process' NZBs are total nzbs, inside the parenthesis is distinct nzbs and 'In
							Database' are
							nzbs that have all parts available and will be processed on next run.<br/><br/>
							The 'In Process' requestid is the number waiting to be processed and inside the parenthesis
							is the
							number changed since the script started. The 'In Database' is the total matches of releases
							to
							requestIDs and inside the parenthesis is percentage of total releases that you have matched
							to a
							requestid.<br/><br/>
							The 'In Process' rows PC and Pron are simply subsets of the 'In Process' row Misc. There is
							no
							postprocessing specifically for these categories. The 'In Database' is the actual count for
							the
							category.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Sequential</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="sequential">Run Sequential:</label></td>
					<td>
						{html_options style="width:180px;" class="siteeditstyle" id="sequential" name='sequential' values=$sequential_ids output=$sequential_names selected=$site->sequential}
						<div class="hint">Sequential runs update_binaries, backfill and update
							releases_sequentially.<br/>Changing
							requires restart.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="seq_timer">Sequential Sleep Timer:</label></td>
					<td>
						<input id="seq_timer" name="seq_timer" class="short" type="text" value="{$site->seq_timer}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="explain">Information:</label></td>
					<td>
						<div class="explanation">I do not recommend using sequential, as it is not tested enough.</div>
					</td>
				</tr>

			</table>
		</fieldset>

		<fieldset>
			<legend>Update Binaries</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="binaries">Update Binaries:</label></td>
					<td>
						{html_options style="width:180px;" class="siteeditstyle" id="binaries" name='binaries' values=$binaries_ids output=$binaries_names selected=$site->binaries}
						<div class="hint">Choose to run update_binaries. Update binaries gets from your last_record to
							now.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="bins_timer">Update Binaries Sleep Timer:</label></td>
					<td>
						<input id="bins_timer" name="bins_timer" class="short" type="text" value="{$site->bins_timer}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="bins_kill_timer">Binaries Kill Timer:</label></td>
					<td>
						<input id="bins_kill_timer" name="bins_kill_timer" class="short" type="text"
							   value="{$site->bins_kill_timer}"/>

						<div class="hint">The time update_binaries is allowed to run with no updates.Time is in
							minutes.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Backfill</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="backfill">Backfill:</label></td>
					<td>
						{html_options style="width:180px;" class="siteeditstyle" id="backfill" name='backfill' values=$backfill_ids output=$backfill_names selected=$site->backfill}
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{html_options style="width:180px;" class="siteeditstyle" id="backfill_order" name='backfill_order' values=$backfill_group_ids output=$backfill_group selected=$site->backfill_order}
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{html_options style="width:180px;" class="siteeditstyle" id="backfill_days" name='backfill_days' values=$backfill_days_ids output=$backfill_days selected=$site->backfill_days}
						<div class="hint">Choose to run backfill type. Backfill gets from your first_record back.<br/>
							Disabled - Disables backfill from running.<br/>
							Safe - Backfills 1 group by backfill days (set in admin-view groups), using the number of
							threads set in admin. This downloads Backfill Quantity times the Backfill Threads, each
							loop.<br/>
							example: you have Backfill Threads = 10, Backfill Quantity = 20k, Max Messages = 5k: you
							will run 10
							threads, queue of 40 and download 200k headers.<br/>
							All - Backfills groups completely. This downloads Backfill Groups by Backfill Quantity (set
							in tmux) times the Backfill Threads (set in admin-edit site), each loop.<br/>
							Newest - Sorts the group selection with the least backfill days backfilled, first.<br/>
							Oldest - Sorts the group selection with the most backfill days backfilled, first.<br/>
							Alphabetical - Sorts the group selection from a to z.<br/>
							Alphabetical Reverse - Sorts the group selection from z to a.<br/>
							Most Posts - Sorts the group selection by the highest number of posts, first.<br/>
							Fewest Posts - Sorts the group selection by the lowest number of posts, first.<br/>
							Backfill days - Days per Group from admin->view group or the Safe Backfill Date from
							admin->edit group.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="backfill_qty">Backfill Quantity:</label></td>
					<td>
						<input id="backfill_qty" name="backfill_qty" class="short" type="text"
							   value="{$site->backfill_qty}"/>

						<div class="hint">When not running backfill intervals, you select the number of headers per
							group per
							thread to download.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="backfill_groups">Backfill Groups:</label></td>
					<td>
						<input id="backfill_groups" name="backfill_groups" class="short" type="text"
							   value="{$site->backfill_groups}"/>

						<div class="hint">When running backfill the groups are sorted by the backfill method chosen
							above.
							Select the number of groups to backfill per loop.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="back_timer">Backfill Sleep Timer:</label></td>
					<td>
						<input id="back_timer" name="back_timer" class="short" type="text" value="{$site->back_timer}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="progressive">Variable Sleep Timer:</label></td>
					<td>
						{html_radios id="progressive" name='progressive' values=$yesno_ids output=$yesno_names selected=$site->progressive separator='<br />'}
						<div class="hint">This will vary the backfill sleep depending on how many collections you
							have.<br/>ie
							50k collections would make sleep timer 100 seconds and 20k releases would make sleep timer
							40
							seconds.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Import nzbs</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="import">Import nzbs:</label></td>
					<td>
						{html_options style="width:180px;" class="siteeditstyle" id="import" name='import' values=$import_ids output=$import_names selected=$site->import}
						<div class="hint">Choose to run import nzb script true/false. This can point to a single folder
							with
							multiple subfolders on just the one folder. If you run this threaded, it will run 1 folder
							per
							thread.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="nzbs">Nzbs Folder:</label></td>
					<td>
						<input id="nzbs" class="long" name="nzbs" type="text" value="{$site->nzbs}"/>

						<div class="hint">Set the path to the nzb dump you downloaded from torrents, this is the path to
							bulk
							files folder of nzbs. This is by default, recursive and threaded. You set the threads in
							Advanced
							Settings.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="import_count">Import nzbs per process:</label></td>
					<td>
						<input id="import_count" name="import_count" class="short" type="text"
							   value="{$site->import_count}"/>
						<div class="hint">How many NZB files to import per process.</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="import_timer">Import nzbs Sleep Timer:</label></td>
					<td>
						<input id="import_timer" name="import_timer" class="short" type="text"
							   value="{$site->import_timer}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="explain">Information:</label></td>
					<td>
						<div class="explanation">This will import all nzbs in the given path. If in your path you have
							nzbs in
							the root folder and subfolders(regardless of nzbs inside), threaded scripts will ignore all
							nzbs in
							the root path. Then each subfolder is threaded.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Update Releases</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="releases">Update Releases:</label></td>
					<td>
						{html_options style="width:180px;" class="siteeditstyle" id="releases" name='releases' values=$releases_ids output=$releases_names selected=$site->releases}
						<div class="hint">Create releases, this is really only necessary to turn off when you only want
							to post
							process.<br/>.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="rel_timer">Update Releases Sleep Timer:</label></td>
					<td>
						<input id="rel_timer" name="rel_timer" class="short" type="text" value="{$site->rel_timer}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Postprocessing</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="post">Postprocess Additional:</label></td>
					<td>
						{html_options style="width:180px;" class="siteeditstyle" id="post" name='post' values=$post_ids output=$post_names selected=$site->post}
						<div class="hint">Choose to do deep rar inspection, preview and sample creation and/or nfo
							processing.
							true/false
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="post_timer">Postprocess Additional Sleep Timer:</label></td>
					<td>
						<input id="post_timer" name="post_timer" class="short" type="text" value="{$site->post_timer}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="post_kill_timer">Postprocess Kill Timer:</label></td>
					<td>
						<input id="post_kill_timer" name="post_kill_timer" class="short" type="text"
							   value="{$site->post_kill_timer}"/>

						<div class="hint">The time postprocess is allowed to run with no updates to the screen.Time is
							in
							seconds. Activity is detected when the history for the pane changes. The clock is restarted
							every
							time activity is detected.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="post_amazon">Postprocess Amazon:</label></td>
					<td>
						{html_radios id="post_amazon" name='post_amazon' values=$yesno_ids output=$yesno_names selected=$site->post_amazon separator='<br />'}
						<div class="hint">Choose to do books, music and games lookups true/false</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="post_timer_amazon">Postprocess Amazon Sleep Timer:</label></td>
					<td>
						<input id="post_timer_amazon" name="post_timer_amazon" class="short" type="text"
							   value="{$site->post_timer_amazon}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="post_non">Postprocess Non-Amazon:</label></td>
					<td>
						{html_radios id="post_non" name='post_non' values=$yesno_ids output=$yesno_names selected=$site->post_non separator='<br />'}
						<div class="hint">Choose to do movies, anime and tv lookups. true/false</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="post_timer">Postprocess Non-Amazon Sleep Timer:</label></td>
					<td>
						<input id="post_timer_non" name="post_timer_non" class="short" type="text"
							   value="{$site->post_timer_non}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Fix Release Names</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="fix_names">Fix Release Names:</label></td>
					<td>
						{html_radios id="fix_names" name='fix_names' values=$yesno_ids output=$yesno_names selected=$site->fix_names separator='<br />'}
						<div class="hint">Choose to try to fix Releases Names using NFOs, par2 files, filenames, md5 and
							sha1. true/false
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="fix_timer">Fix Release Names Sleep Timer:</label></td>
					<td>
						<input id="fix_timer" name="fix_timer" class="short" type="text" value="{$site->fix_timer}"/>

						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Remove Crap Releases</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="fix_crap">Remove Crap Releases:</label></td>
					<td>
						{html_radios id="fix_crap_opt" name='fix_crap_opt' onchange="enableFixCrapCustom()" values=$fix_crap_radio_names output=$fix_crap_radio_names selected=$site->fix_crap_opt separator='<br />'}
						<br>
						<div class="checkbox-grid">
							{if $site->fix_crap_opt == "Custom"}
								{html_checkboxes id="fix_crap" name='fix_crap' values=$fix_crap_check_names output=$fix_crap_check_names selected=explode(', ', $site->fix_crap)}
							{else}
								{html_checkboxes id="fix_crap" name='fix_crap' disabled="true" readonly="true" values=$fix_crap_check_names output=$fix_crap_check_names selected=explode(', ', $site->fix_crap)}
							{/if}
						</div>
						<div class="hint">Choose to run Remove Crap Releases. You can enable all or some.</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="crap_timer">Remove Crap Releases Sleep Timer:</label></td>
					<td>
						<input id="crap_timer" name="crap_timer" class="short" type="text" value="{$site->crap_timer}"/>
						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>
		<fieldset>
			<legend>Decrypt Hashes</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="dehash">Decrypt Hash Based Release Names:</label></td>
					<td>
						{html_options style="width:180px;" class="siteeditstyle" id="dehash" name='dehash' values=$dehash_ids output=$dehash_names selected=$site->dehash}
						<div class="hint">Choose to run Decrypt Hashes true/false</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="dehash_timer">Decrypt Hashes Sleep Timer:</label></td>
					<td>
						<input id="dehash_timer" name="dehash_timer" class="short" type="text"
							   value="{$site->dehash_timer}"/>
						<div class="hint">The time to sleep from the time the loop ends until it is restarted, in
							seconds.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="explain">Information:</label></td>
					<td>
						<div class="explanation">Decrypt hashes works by matching a hashed release to the md5 of a
							release in
							the predb.<br/>
							Included in the same pane is Update Predb. This scrapes several predb sites and then tries
							to match
							against releases.<br/>
							This should be run along with fixReleasenames, this is faster, but only looks at
							releases.names.
							fixReleasenames goes further and looks at release_files.name.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>
		<fieldset>
			<legend>Update TV/Theater</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="update_tv">Update TV and Theater Schedules:</label></td>
					<td>
						{html_radios id="update_tv" name='update_tv' values=$yesno_ids output=$yesno_names selected=$site->update_tv separator='<br />'}
						<div class="hint">Choose to run Update TV and Theater Schedules true/false</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="tv_timer">Update TV and Theater Start Timer:</label></td>
					<td>
						<input id="tv_timer" name="tv_timer" class="short" type="text" value="{$site->tv_timer}"/>

						<div class="hint">This is a start timer. The default is 12 hours. This means that if enabled, is
							will
							start/run every 12 hours, no matter how long it runs for.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>PreDb IRC Scraper</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="scrape_cz">Scrape Irc Channels:</label></td>
					<td>
						{html_radios id="run_ircscraper" name='run_ircscraper' values=$yesno_ids output=$yesno_names
						selected=$site->run_ircscraper separator='<br />'}
						<div class="hint">Choose to run IRCScraper on irc channels. Setting SCRAPE_IRC_C_Z_BOOL
							parameter to
							true or false in settings will toggle between the servers. The pane for this can not be
							created after tmux starts.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="explain">Information:</label></td>
					<td>
						<div class="explanation">IRC Scraper will scrape several predb channels from the enabled
							servers.<br/>
							Copy settings_example to settings in /lib/IRCScraper and change the settings.<br/>
							As a minimum you should set the username and make sure it is unique.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Miscellaneous</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="niceness">Niceness:</label></td>
					<td>
						<input id="niceness" name="niceness" class="short" type="text" value="{$site->niceness}"/>

						<div class="hint">This sets the 'nice'ness of each script, default is 19, the lowest, the
							highest is -20
							anything between -1 and -20 require root/sudo to run
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="collections_kill">Maximum Collections:</label></td>
					<td>
						<input id="collections_kill" name="collections_kill" class="short" type="text"
							   value="{$site->collections_kill}"/>
						<div class="hint">Set this to any number above 0 and when it is exceeded, backfill and update
							binaries
							will be terminated. 0 disables.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="postprocess_kill">Maximum Postprocess:</label></td>
					<td>
						<input id="postprocess_kill" name="postprocess_kill" class="short" type="text"
							   value="{$site->postprocess_kill}"/>

						<div class="hint">Set this to any number above 0 and when it is exceeded, import, backfill and
							update
							binaries will be terminated. 0 disables.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="write_logs">Logging:</label></td>
					<td>
						{html_radios id="write_logs" name='write_logs' values=$yesno_ids output=$yesno_names selected=$site->write_logs separator='<br />'}
						<div class="hint">Set this to write each panes output to a per pane per day log file. This adds
							GMT date
							to the filename.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Server Monitors</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="showquery">Display Query Times:</label></td>
					<td>
						{html_radios id="showquery" name='showquery' values=$yesno_ids output=$yesno_names selected=$site->showquery separator='<br />'}
						<div class="hint">Choose to display the query times for each set of queries. true/false.</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="htop">htop:</label></td>
					<td>
						{html_radios id="htop" name='htop' values=$yesno_ids output=$yesno_names selected=$site->htop separator='<br />'}
						<div class="hint">htop - an interactive process viewer for Linux. The pane for this can not be
							created
							after tmux starts.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="nmon">nmon:</label></td>
					<td>
						{html_radios id="nmon" name='nmon' values=$yesno_ids output=$yesno_names selected=$site->nmon separator='<br />'}
						<div class="hint">nmon is short for Nigel's performance Monitor for Linux. The pane for this can
							not be
							created after tmux starts.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="vnstat">vnstat:</label></td>
					<td>
						{html_radios id="vnstat" name='vnstat' values=$yesno_ids output=$yesno_names selected=$site->vnstat separator='<br />'}
						<input id="vnstat_args" name="vnstat_args" class="text" type="text"
							   value="{$site->vnstat_args}"/>

						<div class="hint">vnStat is a console-based network traffic monitor for Linux and BSD that keeps
							a log
							of network traffic for the selected interface(s). Any additional arguments should be placed
							in the
							text box. The pane for this can not be created after tmux starts.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="tcptrack">tcptrack:</label></td>
					<td>
						{html_radios id="tcptrack" name='tcptrack' values=$yesno_ids output=$yesno_names selected=$site->tcptrack separator='<br />'}
						<input id="tcptrack_args" name="tcptrack_args" class="text" type="text"
							   value="{$site->tcptrack_args}"/>

						<div class="hint">tcptrack displays the status of TCP connections that it sees on a given
							network
							interface. tcptrack monitors their state and displays information such as state,
							source/destination
							addresses and bandwidth usage in a sorted, updated list very much like the top(1) command.
							<br/>Any
							additional arguments should be placed in the text box. <br/>You may need to run "sudo setcap
							cap_net_raw+ep /usr/bin/tcptrack", to be able to run as user. The pane for this can not be
							created
							after tmux starts.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="bwmng">bwm-ng:</label></td>
					<td>
						{html_radios id="bwmng" name='bwmng' values=$yesno_ids output=$yesno_names selected=$site->bwmng separator='<br />'}
						<div class="hint">bwm-ng can be used to monitor the current bandwidth of all or some specific
							network
							interfaces or disks (or partitions). The pane for this can not be created after tmux starts.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="mytop">mytop:</label></td>
					<td>
						{html_radios id="mytop" name='mytop' values=$yesno_ids output=$yesno_names selected=$site->mytop separator='<br />'}
						<div class="hint">mytop - display MySQL server performance info like `top'. <br/>You will need
							to create
							~/.mytop, an example can be found in 'perldoc mytop'. The pane for this can not be created
							after
							tmux starts.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="redis">redis:</label></td>
					<td>
						{html_radios id="redis" name='redis' values=$yesno_ids output=$yesno_names selected=$site->redis separator='<br />'}
						<div class="hint">redis-stat - Monitor your redis instance statistics. The pane for this can not
							be created
							after tmux starts.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="showprocesslist">Show Process List:</label></td>
					<td>
						{html_radios id="showprocesslist" name='showprocesslist' values=$yesno_ids output=$yesno_names selected=$site->showprocesslist separator='<br />'}
						<input id="processupdate" name="processupdate" class="short" type="text"
							   value="{$site->processupdate}"/>

						<div class="hint">This runs the watch command in its own pane. This could be used for a live
							'slow query
							monitor'. Just set the time above and only the queries that exceed it will be
							displayed.<br/>mysql
							-e "SELECT time, state, rows_examined, info FROM information_schema.processlist WHERE
							command !=
							\"Sleep\" AND time >= .5 ORDER BY time DESC \G"<br/>This shows a grid like layout with the
							full test
							of the running queries.<br/>You will need to create a ~/.my.cnf for this to work properlly.
							The pane
							for this can not be created after tmux starts and modifying the time above will have no
							effect until
							a restart occurs.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="console">Console:</label></td>
					<td>
						{html_radios id="console" name='console' values=$yesno_ids output=$yesno_names selected=$site->console separator='<br />'}
						<div class="hint">Open an empty bash shell. The pane for this can not be created after tmux
							starts.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<input class="btn btn-success" type="submit" value="Save Tmux Settings"/>

	</form>
</div>

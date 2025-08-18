<div class="card">
		    <div class="card-header">
		        <div class="d-flex justify-content-between align-items-center">
		            <h4 class="mb-0">{$title}</h4>
		        </div>
		    </div>

		    <div class="card-body">
		        <form action="tmux-edit?action=submit" method="post" id="tmuxForm">
		            {{csrf_field()}}

		            {if isset($error) && $error != ''}
		                <div class="alert alert-danger mb-4">{$error}</div>
		            {/if}

		            <div class="mb-4">
		                <h5 class="border-bottom pb-2 mb-3">Tmux - How It Works</h5>
		            </div>

		            <div class="row mb-4">
		                <div class="col-lg-3 col-md-4">
		                    <label class="form-label fw-bold">Information:</label>
		                </div>
		                <div class="col-lg-9 col-md-8">
		                    <div class="card bg-light">
		                        <div class="card-body">
		                            <p>Tmux is a screen multiplexer and at least version 1.6 is required. It is used here to allow multiple windows per session and multiple panes per window.</p>

		                            <p>Each script is run in its own shell environment. It is not looped, but allowed to run once and then exit. This notifies tmux that the pane is dead and can then be respawned with another iteration of the script in a new shell environment.</p>

		                            <p>This allows for scripts that crash to be restarted without user intervention.</p>

		                            <p>You can run multiple tmux sessions, but they all must have an associated tmux.conf file and all sessions must use the same tmux.conf file.</p>

		                            <div class="alert alert-warning mt-3">
		                                <h5 class="alert-heading">NOTICE:</h5>
		                                <p class="mb-0">If "Save Tmux Settings" is the last thing you did on this page or if it is the active element and if you have this page set to autorefresh or you refresh instead of following a link to this page, you will set the db with the settings currently on this page, not reload from db. This could cause tmux scripts to start while optimize or patch the database is running.</p>
		                            </div>
		                        </div>
		                    </div>
		                </div>
		            </div>

		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Monitor Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="running" class="form-label fw-bold">Tmux Scripts Running:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-power-off"></i></span>
		            <select id="running" name="running" class="form-select">
		                {html_options values=$yesno_ids output=$yesno_names selected=$site->running}
		            </select>
		        </div>
		        <small class="text-muted">This is the shutdown switch. When on, scripts run; when off, all scripts are terminated. This will start/stop all panes without terminating the monitor pane. This is not instant - it doesn't kill any panes but prevents them from restarting.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="monitor_delay" class="form-label fw-bold">Monitor Loop Timer:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
		            <input id="monitor_delay" name="monitor_delay" class="form-control" type="text" value="{$site->monitor_delay}"/>
		            <span class="input-group-text">seconds</span>
		        </div>
		        <small class="text-muted">The time between query refreshes of monitor information, in seconds. This has no effect on any other pane, except for the kill switches. Other panes are checked every 10 seconds. The lower the number, the more often it queries the database. <strong>As the database grows larger, these queries take longer to process.</strong> This has been mitigated by using memcache on the count queries, which stay in cache for the duration set in config.ini (default is 900 seconds).</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="tmux_session" class="form-label fw-bold">Tmux Session:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-terminal"></i></span>
		            <input id="tmux_session" name="tmux_session" class="form-control" type="text" value="{$site->tmux_session}"/>
		        </div>
		        <small class="text-muted">Enter the session name to be used by tmux. No spaces allowed in the name. This can't be changed after scripts start. If you're running multiple servers, you could put your hostname here.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label class="form-label fw-bold">Information:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="card bg-light">
		            <div class="card-body">
		                <p>Monitor is the script that oversees all tmux panes and windows. It stops/starts scripts based on user settings and queries the database to provide stats from your newznab database.</p>

		                <p>There are 2 columns of numbers, 'In Process' and 'In Database':</p>
		                <ul>
		                    <li>'In Process' shows all releases that need to be postprocessed</li>
		                    <li>'In Database' shows the number of releases matching that category</li>
		                </ul>

		                <p>The 'In Process' column has 2 sets of numbers: the total for each category that needs to be postprocessed and (in parentheses) the difference since the script started.</p>

		                <p>The 'In Database' column also has 2 sets of numbers: the total releases for each category and (in parentheses) the percentage relative to the total number of releases.</p>

		                <p>The Misc row has different meanings in both columns:</p>
		                <ul>
		                    <li>In 'In Process', it shows all releases that haven't had 'Additional' run on them (100% of all releases, not just Misc Category)</li>
		                    <li>In 'In Database', it shows releases not categorized in any other category</li>
		                </ul>

		                <p>The counts for parts, binaries and predb totals are estimates and can vary between queries. With InnoDB, it's too slow to query the database for exact counts. All other counts are accurate.</p>

		                <p>'In Process' predb shows the total unmatched predb with (matched changes since start) in parentheses. 'In Database' shows total matched predbs with (percentage of total releases matched to predb) in parentheses.</p>

		                <p>'In Process' NZBs shows total nzbs with (distinct nzbs) in parentheses. 'In Database' shows nzbs with all parts available, ready for next processing run.</p>

		                <p>'In Process' requestid shows the number waiting to be processed with (changes since start) in parentheses. 'In Database' shows total matches of releases to requestIDs with (percentage of total releases matched) in parentheses.</p>

		                <p>'In Process' rows PC and Pron are subsets of the 'In Process' Misc row. There's no specific postprocessing for these categories. 'In Database' shows the actual count for each category.</p>
		            </div>
		        </div>
		    </div>
		</div>

		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Sequential Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="sequential" class="form-label fw-bold">Run Sequential:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-list-ol"></i></span>
		            <select id="sequential" name="sequential" class="form-select">
		                {html_options values=$sequential_ids output=$sequential_names selected=$site->sequential}
		            </select>
		        </div>
		        <small class="text-muted">Sequential runs update_binaries, backfill and update releases sequentially. Changing requires restart.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="seq_timer" class="form-label fw-bold">Sequential Sleep Timer:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
		            <input id="seq_timer" name="seq_timer" class="form-control" type="text" value="{$site->seq_timer}"/>
		            <span class="input-group-text">seconds</span>
		        </div>
		        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label class="form-label fw-bold">Information:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="alert alert-warning">
		            <i class="fa fa-exclamation-triangle me-2"></i>I do not recommend using sequential, as it is not tested enough.
		        </div>
		    </div>
		</div>

		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Update Binaries Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="binaries" class="form-label fw-bold">Update Binaries:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-refresh"></i></span>
		            <select id="binaries" name="binaries" class="form-select">
		                {html_options values=$binaries_ids output=$binaries_names selected=$site->binaries}
		            </select>
		        </div>
		        <small class="text-muted">Choose to run update_binaries. Update binaries gets from your last_record to now.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="bins_timer" class="form-label fw-bold">Update Binaries Sleep Timer:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
		            <input id="bins_timer" name="bins_timer" class="form-control" type="text" value="{$site->bins_timer}"/>
		            <span class="input-group-text">seconds</span>
		        </div>
		        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="bins_kill_timer" class="form-label fw-bold">Binaries Kill Timer:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-stop-circle"></i></span>
		            <input id="bins_kill_timer" name="bins_kill_timer" class="form-control" type="text" value="{$site->bins_kill_timer}"/>
		            <span class="input-group-text">minutes</span>
		        </div>
		        <small class="text-muted">The time update_binaries is allowed to run with no updates. Time is in minutes.</small>
		    </div>
		</div>

		<div class="mb-4">
		    <h5 class="border-bottom pb-2 mb-3">Backfill Settings</h5>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="backfill" class="form-label fw-bold">Backfill:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="row gx-2 mb-2">
		            <div class="col-md-4">
		                <div class="input-group">
		                    <span class="input-group-text"><i class="fa fa-history"></i></span>
		                    <select id="backfill" name="backfill" class="form-select">
		                        {html_options values=$backfill_ids output=$backfill_names selected=$site->backfill}
		                    </select>
		                </div>
		            </div>
		            <div class="col-md-4">
		                <div class="input-group">
		                    <span class="input-group-text"><i class="fa fa-sort"></i></span>
		                    <select id="backfill_order" name="backfill_order" class="form-select">
		                        {html_options values=$backfill_group_ids output=$backfill_group selected=$site->backfill_order}
		                    </select>
		                </div>
		            </div>
		            <div class="col-md-4">
		                <div class="input-group">
		                    <span class="input-group-text"><i class="fa fa-calendar"></i></span>
		                    <select id="backfill_days" name="backfill_days" class="form-select">
		                        {html_options values=$backfill_days_ids output=$backfill_days selected=$site->backfill_days}
		                    </select>
		                </div>
		            </div>
		        </div>
		        <small class="text-muted">
		            Choose to run backfill type. Backfill gets from your first_record back.<br/>
		            <strong>Disabled</strong> - Disables backfill from running.<br/>
		            <strong>Safe</strong> - Backfills 1 group by backfill days (set in admin-view groups), using the number of threads set in admin. This downloads Backfill Quantity times the Backfill Threads, each loop.<br/>
		            <em>Example:</em> you have Backfill Threads = 10, Backfill Quantity = 20k, Max Messages = 5k: you will run 10 threads, queue of 40 and download 200k headers.<br/>
		            <strong>All</strong> - Backfills groups completely. This downloads Backfill Groups by Backfill Quantity (set in tmux) times the Backfill Threads (set in admin-edit site), each loop.<br/>
		            <strong>Newest</strong> - Sorts the group selection with the least backfill days backfilled, first.<br/>
		            <strong>Oldest</strong> - Sorts the group selection with the most backfill days backfilled, first.<br/>
		            <strong>Alphabetical</strong> - Sorts the group selection from a to z.<br/>
		            <strong>Alphabetical Reverse</strong> - Sorts the group selection from z to a.<br/>
		            <strong>Most Posts</strong> - Sorts the group selection by the highest number of posts, first.<br/>
		            <strong>Fewest Posts</strong> - Sorts the group selection by the lowest number of posts, first.<br/>
		            <strong>Backfill days</strong> - Days per Group from admin->view group or the Safe Backfill Date from admin->edit group.
		        </small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="backfill_qty" class="form-label fw-bold">Backfill Quantity:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-database"></i></span>
		            <input id="backfill_qty" name="backfill_qty" class="form-control" type="text" value="{$site->backfill_qty}"/>
		        </div>
		        <small class="text-muted">When not running backfill intervals, you select the number of headers per group per thread to download.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="backfill_groups" class="form-label fw-bold">Backfill Groups:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-layer-group"></i></span>
		            <input id="backfill_groups" name="backfill_groups" class="form-control" type="text" value="{$site->backfill_groups}"/>
		        </div>
		        <small class="text-muted">When running backfill the groups are sorted by the backfill method chosen above. Select the number of groups to backfill per loop.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="back_timer" class="form-label fw-bold">Backfill Sleep Timer:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <div class="input-group">
		            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
		            <input id="back_timer" name="back_timer" class="form-control" type="text" value="{$site->back_timer}"/>
		            <span class="input-group-text">seconds</span>
		        </div>
		        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
		    </div>
		</div>

		<div class="row mb-4">
		    <div class="col-lg-3 col-md-4">
		        <label for="progressive" class="form-label fw-bold">Variable Sleep Timer:</label>
		    </div>
		    <div class="col-lg-9 col-md-8">
		        <select id="progressive" name="progressive" class="form-select">
		            {html_options values=$yesno_ids output=$yesno_names selected=$site->progressive}
		        </select>
		        <small class="text-muted">This will vary the backfill sleep depending on how many collections you have.<br/>Example: 50k collections would make sleep timer 100 seconds and 20k releases would make sleep timer 40 seconds.</small>
		    </div>
		</div>

		<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Update Releases Settings</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="releases" class="form-label fw-bold">Update Releases:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-refresh"></i></span>
            <select id="releases" name="releases" class="form-select">
                {html_options values=$releases_ids output=$releases_names selected=$site->releases}
            </select>
        </div>
        <small class="text-muted">Create releases, this is really only necessary to turn off when you only want to post process.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="rel_timer" class="form-label fw-bold">Update Releases Sleep Timer:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            <input id="rel_timer" name="rel_timer" class="form-control" type="text" value="{$site->rel_timer}"/>
            <span class="input-group-text">seconds</span>
        </div>
        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
    </div>
</div>

<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Postprocessing Settings</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="post" class="form-label fw-bold">Postprocess Additional:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-tasks"></i></span>
            <select id="post" name="post" class="form-select">
                {html_options values=$post_ids output=$post_names selected=$site->post}
            </select>
        </div>
        <small class="text-muted">Choose to do deep rar inspection, preview and sample creation and/or nfo processing.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="post_timer" class="form-label fw-bold">Postprocess Additional Sleep Timer:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            <input id="post_timer" name="post_timer" class="form-control" type="text" value="{$site->post_timer}"/>
            <span class="input-group-text">seconds</span>
        </div>
        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="post_kill_timer" class="form-label fw-bold">Postprocess Kill Timer:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-stop-circle"></i></span>
            <input id="post_kill_timer" name="post_kill_timer" class="form-control" type="text" value="{$site->post_kill_timer}"/>
            <span class="input-group-text">seconds</span>
        </div>
        <small class="text-muted">The time postprocess is allowed to run with no updates to the screen. Time is in seconds. Activity is detected when the history for the pane changes. The clock is restarted every time activity is detected.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="post_amazon" class="form-label fw-bold">Postprocess Amazon:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="post_amazon" name="post_amazon" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->post_amazon}
        </select>
        <small class="text-muted">Choose to do books, music and games lookups.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="post_timer_amazon" class="form-label fw-bold">Postprocess Amazon Sleep Timer:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            <input id="post_timer_amazon" name="post_timer_amazon" class="form-control" type="text" value="{$site->post_timer_amazon}"/>
            <span class="input-group-text">seconds</span>
        </div>
        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="post_non" class="form-label fw-bold">Postprocess Non-Amazon:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="post_non" name="post_non" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->post_non}
        </select>
        <small class="text-muted">Choose to do movies, anime and tv lookups.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="post_timer_non" class="form-label fw-bold">Postprocess Non-Amazon Sleep Timer:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            <input id="post_timer_non" name="post_timer_non" class="form-control" type="text" value="{$site->post_timer_non}"/>
            <span class="input-group-text">seconds</span>
        </div>
        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
    </div>
</div>
		<!-- Fix Release Names Section -->
<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Fix Release Names</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="fix_names" class="form-label fw-bold">Fix Release Names:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="fix_names" name="fix_names" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->fix_names}
        </select>
        <small class="text-muted">Choose to try to fix Releases Names using NFOs, par2 files, filenames, md5 and sha1. true/false</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="fix_timer" class="form-label fw-bold">Fix Release Names Sleep Timer:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            <input id="fix_timer" name="fix_timer" class="form-control" type="text" value="{$site->fix_timer}"/>
            <span class="input-group-text">seconds</span>
        </div>
        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
    </div>
</div>

<!-- Remove Crap Releases Section -->
<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Remove Crap Releases</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="fix_crap_opt" class="form-label fw-bold">Remove Crap Releases:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group mb-2">
            <span class="input-group-text"><i class="fa fa-filter"></i></span>
            <select id="fix_crap_opt" name="fix_crap_opt" class="form-select" onchange="fixCrapSyncUI()">
                {html_options values=$fix_crap_radio_names output=$fix_crap_radio_names selected=$site->fix_crap_opt}
            </select>
        </div>

        {assign var=isCustom value=($site->fix_crap_opt == "Custom")}
        {assign var=isAll value=($site->fix_crap_opt == "All")}
        {assign var=isDisabled value=($site->fix_crap_opt == "Disabled")}
        {if $isAll}
            {assign var=fixCrapSelected value=$fix_crap_check_names}
            {assign var=fixCrapHint value='All filters are applied.'}
        {elseif $isDisabled}
            {assign var=fixCrapSelected value=''}
            {assign var=fixCrapHint value='All filters are disabled.'}
        {else}
            {assign var=fixCrapSelected value=explode(', ', $site->fix_crap)}
            {assign var=fixCrapHint value='Select specific filters below.'}
        {/if}

        <div id="fix-crap-panel" class="border rounded p-3 {if !$isCustom}opacity-50{/if}">
            <label for="fix-crap-select" class="form-label mb-2">Filters</label>
            <select id="fix-crap-select" name="fix_crap[]" class="form-select" multiple size="8" {if !$isCustom}disabled="disabled" aria-disabled="true"{else}aria-disabled="false"{/if} data-custom-default="{$site->fix_crap}">
                {html_options values=$fix_crap_check_names output=$fix_crap_check_names selected=$fixCrapSelected}
            </select>
            <div id="fix-crap-actions" class="mt-2 {if !$isCustom}d-none{/if}">
                <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="fixCrapSelectAll()">Select all</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fixCrapClearAll()">Clear all</button>
            </div>
        </div>

        <div id="fix-crap-hint" class="form-text {if $isCustom}d-none{/if}">{$fixCrapHint}</div>
        <small class="text-muted d-block mt-1">Choose to run Remove Crap Releases. You can enable all or some.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="crap_timer" class="form-label fw-bold">Remove Crap Releases Sleep Timer:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            <input id="crap_timer" name="crap_timer" class="form-control" type="text" value="{$site->crap_timer}"/>
            <span class="input-group-text">seconds</span>
        </div>
        <small class="text-muted">The time to sleep from the time the loop ends until it is restarted, in seconds.</small>
    </div>
</div>

<!-- PreDb IRC Scraper Section -->
<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">PreDb IRC Scraper</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="run_ircscraper" class="form-label fw-bold">Scrape Irc Channels:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="run_ircscraper" name="run_ircscraper" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->run_ircscraper}
        </select>
        <small class="text-muted">Choose to run IRCScraper on irc channels. Setting SCRAPE_IRC_C_Z_BOOL parameter to true or false in settings will toggle between the servers. The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="explain" class="form-label fw-bold">Information:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="card bg-light">
            <div class="card-body">
                IRC Scraper will scrape several predb channels from the enabled servers.<br/>
                Copy settings_example to settings in /lib/IRCScraper and change the settings.<br/>
                As a minimum you should set the username and make sure it is unique.
            </div>
        </div>
    </div>
</div>

<!-- Miscellaneous Section -->
<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Miscellaneous</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="niceness" class="form-label fw-bold">Niceness:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-sliders"></i></span>
            <input id="niceness" name="niceness" class="form-control" type="text" value="{$site->niceness}"/>
        </div>
        <small class="text-muted">This sets the 'nice'ness of each script, default is 19, the lowest, the highest is -20; anything between -1 and -20 require root/sudo to run.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="collections_kill" class="form-label fw-bold">Maximum Collections:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-stop-circle"></i></span>
            <input id="collections_kill" name="collections_kill" class="form-control" type="text" value="{$site->collections_kill}"/>
        </div>
        <small class="text-muted">Set this to any number above 0 and when it is exceeded, backfill and update binaries will be terminated. 0 disables.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="postprocess_kill" class="form-label fw-bold">Maximum Postprocess:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <div class="input-group">
            <span class="input-group-text"><i class="fa fa-stop-circle"></i></span>
            <input id="postprocess_kill" name="postprocess_kill" class="form-control" type="text" value="{$site->postprocess_kill}"/>
        </div>
        <small class="text-muted">Set this to any number above 0 and when it is exceeded, import, backfill and update binaries will be terminated. 0 disables.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="write_logs" class="form-label fw-bold">Logging:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="write_logs" name="write_logs" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->write_logs}
        </select>
        <small class="text-muted">Set this to write each panes output to a per pane per day log file. This adds GMT date to the filename.</small>
    </div>
</div>

<!-- Server Monitors Section -->
<div class="mb-4">
    <h5 class="border-bottom pb-2 mb-3">Server Monitors</h5>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="showquery" class="form-label fw-bold">Display Query Times:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="showquery" name="showquery" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->showquery}
        </select>
        <small class="text-muted">Choose to display the query times for each set of queries. true/false.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="htop" class="form-label fw-bold">htop:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="htop" name="htop" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->htop}
        </select>
        <small class="text-muted">htop - an interactive process viewer for Linux. The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="nmon" class="form-label fw-bold">nmon:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="nmon" name="nmon" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->nmon}
        </select>
        <small class="text-muted">nmon is short for Nigel's performance Monitor for Linux. The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="vnstat" class="form-label fw-bold">vnstat:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="vnstat" name="vnstat" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->vnstat}
        </select>
        <div class="input-group mt-2">
            <span class="input-group-text"><i class="fa fa-cog"></i></span>
            <input id="vnstat_args" name="vnstat_args" class="form-control" type="text" value="{$site->vnstat_args}"/>
        </div>
        <small class="text-muted">vnStat is a console-based network traffic monitor for Linux and BSD that keeps a log of network traffic for the selected interface(s). Any additional arguments should be placed in the text box. The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="tcptrack" class="form-label fw-bold">tcptrack:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="tcptrack" name="tcptrack" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->tcptrack}
        </select>
        <div class="input-group mt-2">
            <span class="input-group-text"><i class="fa fa-cog"></i></span>
            <input id="tcptrack_args" name="tcptrack_args" class="form-control" type="text" value="{$site->tcptrack_args}"/>
        </div>
        <small class="text-muted">tcptrack displays the status of TCP connections that it sees on a given network interface. tcptrack monitors their state and displays information such as state, source/destination addresses and bandwidth usage in a sorted, updated list very much like the top(1) command. Any additional arguments should be placed in the text box. You may need to run "sudo setcap cap_net_raw+ep /usr/bin/tcptrack", to be able to run as user. The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="bwmng" class="form-label fw-bold">bwm-ng:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="bwmng" name="bwmng" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->bwmng}
        </select>
        <small class="text-muted">bwm-ng can be used to monitor the current bandwidth of all or some specific network interfaces or disks (or partitions). The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="mytop" class="form-label fw-bold">mytop:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="mytop" name="mytop" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->mytop}
        </select>
        <small class="text-muted">mytop - display MySQL server performance info like `top'. You will need to create ~/.mytop, an example can be found in 'perldoc mytop'. The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="redis" class="form-label fw-bold">redis:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="redis" name="redis" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->redis}
        </select>
        <small class="text-muted">redis-stat - Monitor your redis instance statistics. The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="showprocesslist" class="form-label fw-bold">Show Process List:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="showprocesslist" name="showprocesslist" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->showprocesslist}
        </select>
        <div class="input-group mt-2">
            <span class="input-group-text"><i class="fa fa-clock-o"></i></span>
            <input id="processupdate" name="processupdate" class="form-control" type="text" value="{$site->processupdate}"/>
            <span class="input-group-text">seconds</span>
        </div>
        <small class="text-muted">This runs the watch command in its own pane. This could be used for a live 'slow query monitor'. Just set the time above and only the queries that exceed it will be displayed. mysql -e "SELECT time, state, rows_examined, info FROM information_schema.processlist WHERE command != \"Sleep\" AND time >= .5 ORDER BY time DESC \G". This shows a grid like layout with the full test of the running queries. You will need to create a ~/.my.cnf for this to work properly. The pane for this can not be created after tmux starts and modifying the time above will have no effect until a restart occurs.</small>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-3 col-md-4">
        <label for="console" class="form-label fw-bold">Console:</label>
    </div>
    <div class="col-lg-9 col-md-8">
        <select id="console" name="console" class="form-select">
            {html_options values=$yesno_ids output=$yesno_names selected=$site->console}
        </select>
        <small class="text-muted">Open an empty bash shell. The pane for this can not be created after tmux starts.</small>
    </div>
</div>

<div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
    <button type="submit" class="btn btn-success">
        <i class="fa fa-save me-2"></i>Save Tmux Settings
    </button>
</div>

<script>
(function () {
  var prevVal = null;
  var lastCustom = new Set();

  function getModeEl() { return document.getElementById('fix_crap_opt'); }
  function getMultiEl() { return document.getElementById('fix-crap-select'); }
  function getPanelEl() { return document.getElementById('fix-crap-panel'); }
  function getHintEl() { return document.getElementById('fix-crap-hint'); }
  function getActionsEl() { return document.getElementById('fix-crap-actions'); }
  function getModeVal() { var el = getModeEl(); return el ? el.value : ''; }

  function currentSelectedSet() {
    var el = getMultiEl();
    var set = new Set();
    if (!el) return set;
    Array.prototype.forEach.call(el.options, function (opt) { if (opt.selected) set.add(opt.value); });
    return set;
  }
  function setSelectedFromSet(set) {
    var el = getMultiEl();
    if (!el) return;
    Array.prototype.forEach.call(el.options, function (opt) { opt.selected = set.has(opt.value); });
  }
  function selectAllOptions() {
    var el = getMultiEl();
    if (!el) return;
    Array.prototype.forEach.call(el.options, function (opt) { opt.selected = true; });
  }
  function clearAllOptions() {
    var el = getMultiEl();
    if (!el) return;
    Array.prototype.forEach.call(el.options, function (opt) { opt.selected = false; });
  }

  function fixCrapSelectAll() { selectAllOptions(); }
  function fixCrapClearAll() { clearAllOptions(); }

  function fixCrapSyncUI() {
    var mode = getModeVal();
    var multi = getMultiEl();
    var panel = getPanelEl();
    var hint = getHintEl();
    var actions = getActionsEl();
    if (!multi || !panel) return;

    // Persist custom selection when leaving Custom
    if (prevVal === 'Custom' && mode !== 'Custom') {
      lastCustom = currentSelectedSet();
    }

    if (mode === 'Custom') {
      if (lastCustom && lastCustom.size > 0) {
        setSelectedFromSet(lastCustom);
      }
      multi.removeAttribute('disabled');
      multi.setAttribute('aria-disabled', 'false');
      panel.classList.remove('opacity-50');
      if (hint) hint.classList.add('d-none');
      if (actions) actions.classList.remove('d-none');
    } else if (mode === 'All') {
      selectAllOptions();
      multi.setAttribute('disabled', 'disabled');
      multi.setAttribute('aria-disabled', 'true');
      panel.classList.add('opacity-50');
      if (hint) { hint.textContent = 'All filters are applied.'; hint.classList.remove('d-none'); }
      if (actions) actions.classList.add('d-none');
    } else { // Disabled or unknown
      clearAllOptions();
      multi.setAttribute('disabled', 'disabled');
      multi.setAttribute('aria-disabled', 'true');
      panel.classList.add('opacity-50');
      if (hint) { hint.textContent = 'All filters are disabled.'; hint.classList.remove('d-none'); }
      if (actions) actions.classList.add('d-none');
    }

    prevVal = mode;
  }

  // Expose helpers globally for inline handlers
  window.fixCrapSelectAll = fixCrapSelectAll;
  window.fixCrapClearAll = fixCrapClearAll;
  window.fixCrapSyncUI = fixCrapSyncUI;

  document.addEventListener('DOMContentLoaded', function () {
    // Seed lastCustom from server-provided defaults
    var multi = getMultiEl();
    if (multi) {
      var def = multi.getAttribute('data-custom-default') || '';
      lastCustom = new Set(def ? def.split(/,\s*/) : []);
    }
    prevVal = getModeVal();
    var modeEl = getModeEl();
    if (modeEl) modeEl.addEventListener('change', fixCrapSyncUI);
    fixCrapSyncUI();
  });
})();
</script>

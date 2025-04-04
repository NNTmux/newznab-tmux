<h1>{$title}</h1>

<div class="card card-body">
	<form action="site-edit?action=submit" method="post">
		{{csrf_field()}}

		{if isset ($error) && $error != ''}
			<div class="error">{$error}</div>
		{/if}

		<fieldset>
			<legend>Main Site Settings, HTML Layout, Tags</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td><label for="strapline">Strapline</label>:</td>
					<td>
						<input id="strapline" class="long" name="strapline" type="text" value="{$site->strapline}"/>
						<div class="hint">Displayed in the header on every public page.</div>
					</td>
				</tr>

				<tr>
					<td><label for="metatitle">Meta Title</label>:</td>
					<td>
						<input id="metatitle" class="long" name="metatitle" type="text" value="{$site->metatitle}"/>
						<div class="hint">Stem meta-tag appended to all page title tags.</div>
					</td>
				</tr>


				<tr>
					<td><label for="metadescription">Meta Description</label>:</td>
					<td>
						<textarea id="metadescription" name="metadescription">{$site->metadescription}</textarea>
						<div class="hint">Stem meta-description appended to all page meta description tags.</div>
					</td>
				</tr>

				<tr>
					<td><label for="metakeywords">Meta Keywords</label>:</td>
					<td>
						<textarea id="metakeywords" name="metakeywords">{$site->metakeywords}</textarea>
						<div class="hint">Stem meta-keywords appended to all page meta keyword tags.</div>
					</td>
				</tr>

				<tr>
					<td><label for="footer">Footer</label>:</td>
					<td>
						<textarea id="footer" name="footer">{$site->footer}</textarea>
						<div class="hint">Displayed in the footer section of every public page.</div>
					</td>
				</tr>

				<tr>
					<td><label for="style">Default Home Page</label>:</td>
					<td>
						<input id="home_link" class="long" name="home_link" type="text" value="{$site->home_link}"/>
						<div class="hint">The relative path to a the landing page shown when a user logs in, or clicks
							the home link.
						</div>
					</td>
				</tr>
				<tr>
					<td><label for="style">Dereferrer Link</label>:</td>
					<td>
						<input id="dereferrer_link" class="long" name="dereferrer_link" type="text"
							   value="{$site->dereferrer_link}"/>
						<div class="hint">Optional URL to prepend to external links</div>
					</td>
				</tr>
				<tr>
					<td><label for="tandc">Terms and Conditions</label>:</td>
					<td>
						<textarea id="tandc" name="tandc">{$site->tandc}</textarea>
						<div class="hint">Text displayed in the terms and conditions page.</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Usenet Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="nzbsplitlevel">Nzb File Path Level Deep:</label></td>
					<td>
						<input id="nzbsplitlevel" class="short" name="nzbsplitlevel" type="text"
							   value="{$site->nzbsplitlevel}"/>
						<div class="hint">Levels deep to store the nzb Files.
							<br/><strong>If you change this you must run the misc/testing/DB/nzb-reorg
								script!</strong>
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="partretentionhours">Part Retention Hours:</label></td>
					<td>
						<input class="short" id="partretentionhours" name="partretentionhours" type="text"
							   value="{$site->partretentionhours}"/>
						<div class="hint">The number of hours incomplete parts and binaries will be retained.</div>
					</td>
				</tr>
				<tr>
					<td><label for="releaseretentiondays">Release Retention</label>:</td>
					<td>
						<input class="tiny" id="releasedays" name="releaseretentiondays" type="text"
							   value="{$site->releaseretentiondays}"/>
						<div class="hint">The number of days releases will be retained for use throughout site. Set to 0
							to disable.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="miscotherretentionhours">Other->Misc Retention Hours:</label>
					</td>
					<td>
						<input class="short" id="miscotherretentionhours" name="miscotherretentionhours" type="text"
							   value="{$site->miscotherretentionhours}"/>
						<div class="hint">The number of hours releases categorized as Misc->Other will be retained. Set
							to 0 to disable.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="mischashedretentionhours">Other->Hashed Retention
							Hours:</label></td>
					<td>
						<input class="short" id="mischashedretentionhours" name="mischashedretentionhours" type="text"
							   value="{$site->mischashedretentionhours}"/>
						<div class="hint">The number of hours releases categorized as Misc->Hashed will be retained. Set
							to 0 to disable.
						</div>
					</td>
				</tr>

				<tr>
					<td><label for="partsdeletechunks">Parts Delete In Chunks</label>:</td>
					<td>
						<input class="small" id="partsdeletechunks" name="partsdeletechunks" type="text"
							   value="{$site->partsdeletechunks}"/>
						<div class="hint">Default is 0 (off), which will remove parts in one go. If backfilling or
							importing and parts table is large, using chunks of 5000+ will speed up removal. Normal
							indexing is fastest with this setting at 0.
						</div>
					</td>
				</tr>

				<tr>
					<td><label for="minfilestoformrelease">Minimum Files to Make a Release</label>:</td>
					<td>
						<input class="tiny" id="minfilestoformrelease" name="minfilestoformrelease" type="text"
							   value="{$site->minfilestoformrelease}"/>
						<div class="hint">The minimum number of files to make a release. i.e. if set to two, then
							releases which only contain one file will not be created.
						</div>
					</td>
				</tr>

				<tr>
					<td><label for="minsizetoformrelease">Minimum File Size to Make a Release</label>:</td>
					<td>
						<input class="small" id="minsizetoformrelease" name="minsizetoformrelease" type="text"
							   value="{$site->minsizetoformrelease}"/>
						<div class="hint">The minimum total size in bytes to make a release. If set to 0, then
							ignored.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="maxsizetoformrelease">Maximum File Size to Make a
							Release:</label></td>
					<td>
						<input class="small" id="maxsizetoformrelease" name="maxsizetoformrelease" type="text"
							   value="{$site->maxsizetoformrelease}"/>
						<div class="hint">The maximum total size in bytes to make a release. If set to 0, then ignored.
							Only deletes
							during release creation.
						</div>
					</td>
				</tr>

				<tr>
					<td><label for="completionpercent">Minimum Completion Percent to Make a Release</label>:</td>
					<td>
						<input class="tiny" id="completionpercent" name="completionpercent" type="text"
							   value="{$site->completionpercent}"/>
						<div class="hint">The minimum completion percent to make a release. i.e. if set to 97, then
							releases under 97% completion will not be created. If set to 0, then ignored.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="grabstatus">Update grabs:</label></td>
					<td>
						{html_radios id="grabstatus" name='grabstatus' values=$yesno_ids output=$yesno_names selected=$site->grabstatus separator='<br />'}
						<div class="hint">Whether to update download counts when someone downloads a release.</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="crossposttime">Crossposted Time Check:</label></td>
					<td>
						<input class="short" id="crossposttime" name="crossposttime" type="text"
							   value="{$site->crossposttime}"/>
						<div class="hint">The time in hours to check for crossposted releases - this will delete 1 of
							the releases if the 2 are posted by the same person in the same time period.
						</div>
					</td>
				</tr
				<tr>
					<td><label for="maxmssgs">Max Messages</label>:</td>
					<td>
						<input class="small" id="maxmssgs" name="maxmssgs" type="text" value="{$site->maxmssgs}"/>
						<div class="hint">The maximum number of messages to fetch at a time from the server.</div>
					</td>
				</tr>
				<tr>
					<td><label for="max_headers_iteration">Max headers iteration</label>:</td>
					<td>
						<input class="small" id="max_headers_iteration" name="max_headers_iteration" type="text"
							   value="{$site->max_headers_iteration}"/>
						<div class="hint">The maximum number of headers that update binaries sees as the total range.
							This ensure that a total of no more than this is attempted to be downloaded at one time per
							group.
						</div>
					</td>
				</tr>

				<tr>
					<td><label for="newgroupscanmethod">Where to start new groups</label>:</td>
					<td>
						{html_radios id="newgroupscanmethod" name='newgroupscanmethod' values=$yesno_ids output=$newgroupscan_names selected=$site->newgroupscanmethod separator='<br />'}
						<input class="tiny" id="newgroupdaystoscan" name="newgroupdaystoscan" type="text"
							   value="{$site->newgroupdaystoscan}"/> Days or
						<input class="small" id="newgroupmsgstoscan" name="newgroupmsgstoscan" type="text"
							   value="{$site->newgroupmsgstoscan}"/> Posts<br/>
						<div class="hint">Scan back X (posts/days) for each new group? Can backfill to scan further.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="safebackfilldate">Safe Backfill Date:</label></td>
					<td>
						<input class="small" id="safebackfilldate" name="safebackfilldate" type="text"
							   value="{$site->safebackfilldate}"/>

						<div class="hint">The target date for safe backfill. Format: YYYY-MM-DD</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="disablebackfillgroup">Auto disable groups during
							Backfill:</label></td>
					<td>
						{html_radios id="disablebackfillgroup" name='disablebackfillgroup' values=$yesno_ids output=$yesno_names selected=$site->disablebackfillgroup separator='<br />'}
						<div class="hint">Whether to disable a group automatically during backfill if the target date
							has been reached.
						</div>
					</td>
				</tr>
				<tr>

			</table>
		</fieldset>

		<fieldset>
			<legend>Lookup Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="lookuptv">Lookup TV:</label></td>
					<td>
						{html_options style="width:180px;" id="lookuptv" name='lookuptv' values=$lookuptv_ids output=$lookuptv_names selected=$site->lookuptv}
						<div class="hint">Whether to attempt to lookup TvRage ids on the web.</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="lookupbooks">Lookup Books:</label></td>
					<td>
						{html_options style="width:180px;" id="lookupbooks" name='lookupbooks' values=$lookupbooks_ids output=$lookupbooks_names selected=$site->lookupbooks}
						<div class="hint">Whether to attempt to lookup book information from Amazon.</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="book_reqids">Type of books to look up:</label></td>
					<td>
						{html_options_multiple id="book_reqids" name='book_reqids' values=$book_reqids_ids output=$book_reqids_names selected=$book_reqids_selected}
						<div class="hint">Categories of Books to lookup information for (only work if Lookup Books is
							set to yes).
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="lookupimdb">Lookup Movies:</label></td>
					<td>
						{html_options style="width:180px;" id="lookupimdb" name='lookupimdb' values=$lookupmovies_ids output=$lookupmovies_names selected=$site->lookupimdb}
						<div class="hint">Whether to attempt to lookup film information from IMDB or TheMovieDB.</div>
					</td>
				</tr>

				<tr>
					<td><label for="lookuplanguage">Movie Lookup Language</label>:</td>
					<td>
						{html_options id="lookuplanguage" name='lookuplanguage' values=$lookuplanguage_iso output=$lookuplanguage_names selected=$site->lookuplanguage}
						<div class="hint">Preferred language for scraping external sources.</div>
					</td>
				</tr>

				<tr>
					<td><label for="lookupanidb">Lookup AniDB</label>:</td>
					<td>
						{html_radios id="lookupanidb" name='lookupanidb' values=$yesno_ids output=$yesno_names selected=$site->lookupanidb separator='<br />'}
						<div class="hint">Whether to attempt to lookup anime information from AniDB when processing
							binaries.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="lookupmusic">Lookup Music:</label></td>
					<td>
						{html_options style="width:180px;" id="lookupmusic" name='lookupmusic' values=$lookupmusic_ids output=$lookupmusic_names selected=$site->lookupmusic}
						<div class="hint">Whether to attempt to lookup music information from Amazon.</div>
					</td>
				</tr>
				<tr>
					<td><label for="saveaudiopreview">Save Audio Preview</label>:</td>
					<td>
						{html_radios id="saveaudiopreview" name='saveaudiopreview' values=$yesno_ids output=$yesno_names selected=$site->saveaudiopreview separator='<br />'}
						<div class="hint">Whether to save a preview of an audio release (requires deep rar inspection
							enabled).<br/>It is advisable to specify a path to the lame binary to reduce the size of
							audio previews.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="lookupgames">Lookup Games:</label></td>
					<td>
						{html_options style="width:180px;" id="lookupgames" name='lookupgames' values=$lookupgames_ids output=$lookupgames_names selected=$site->lookupgames}
						<div class="hint">Whether to attempt to lookup game information from Amazon.</div>
					</td>
				</tr>

				<tr>
					<td><label for="lookupxxx">Lookup XXX</label>:</td>
					<td>
						{html_radios id="lookupxxx" name='lookupxxx' values=$yesno_ids output=$yesno_names selected=$site->lookupxxx separator='<br />'}
						<div class="hint">Whether to attempt to lookup XXX information when processing binaries.</div>
					</td>
				</tr>
			</table>
		</fieldset>
		<fieldset>
			<legend>Language/Categorization options</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="categorizeforeign">Categorize Foreign:</label></td>
					<td>
						{html_radios id="categorizeforeign" name='categorizeforeign' values=$yesno_ids output=$yesno_names selected=$site->categorizeforeign separator='<br />'}
						<div class="hint">Whether to send foreign movies/tv to
							foreign sections or not. If set to true they will go in foreign categories.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="catwebdl">Categorize WEB-DL:</label></td>
					<td>
						{html_radios id="catwebdl" name='catwebdl' values=$yesno_ids output=$yesno_names selected=$site->catwebdl separator='<br />'}
						<div class="hint">Whether to send WEB-DL to the WEB-DL section or not. If set to true they will
							go in
							WEB-DL category, false will send them in HD TV.<br/>This will also make them inaccessible to
							Sickbeard and possibly Couchpotato.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Password Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="end">Download last compressed
							file:</label></td>
					<td>
						{html_radios id="end" name='end' values=$yesno_ids output=$yesno_names selected=$site->end separator='<br />'}
						<div class="hint">Try to download the last rar or zip file? (This is good if most of the files
							are at the end.) Note: The first rar/zip is still downloaded.
						</div>
					</td>
				</tr>
				<tr>
					<td><label for="showpasswordedrelease">Show Passworded Releases</label>:</td>
					<td>
						{html_options id="showpasswordedrelease" name='showpasswordedrelease' values=$passworded_ids output=$passworded_names selected=$site->showpasswordedrelease}
						<div class="hint">Whether to show passworded releases in browse,
							search, api and rss
							feeds.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>
		<fieldset>
			<legend>Usenet Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">

				<tr>
					<td style="width:180px;"><label for="maxsizetopostprocess">Maximum Release Size to Post
							Process:</label></td>
					<td>
						<input class="short" id="maxsizetopostprocess" name="maxsizetopostprocess" type="text"
							   value="{$site->maxsizetopostprocess}"/>

						<div class="hint">The maximum size in gigabytes to postprocess a release. If set to 0, then
							ignored.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="minsizetopostprocess">Minimum Release Size to Post
							Process:</label></td>
					<td>
						<input class="short" id="minsizetopostprocess" name="minsizetopostprocess" type="text"
							   value="{$site->minsizetopostprocess}"/>
						<div class="hint">The minimum size in megabytes to post process (additional) a release. If set
							to 0, then ignored.
						</div>
					</td>
				</tr>
				<tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Advanced Settings - For advanced users</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="maxnzbsprocessed">Maximum NZBs stage5:</label></td>
					<td>
						<input class="short" id="maxnzbsprocessed" name="maxnzbsprocessed" type="text"
							   value="{$site->maxnzbsprocessed}"/>
						<div class="hint">The maximum amount of NZB files to create on stage 5 at a time in
							update_releases. If more are to be created it will loop stage 5 until none remain.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="partrepair">Part Repair:</label></td>
					<td>
						{html_radios id="partrepair" name='partrepair' values=$yesno_ids output=$yesno_names selected=$site->partrepair separator='<br />'}
						<div class="hint">Whether to attempt to repair parts or not, increases backfill/binaries
							updating
							time.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="safepartrepair">Part Repair for Backfill Scripts:</label></td>
					<td>
						{html_radios id="safepartrepair" name='safepartrepair' values=$yesno_ids output=$yesno_names selected=$site->safepartrepair separator='<br />'}
						<div class="hint">Whether to put unreceived parts into missed_parts table when running
							binaries(safe) or backfill scripts.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="maxpartrepair">Maximum repair per run:</label></td>
					<td>
						<input class="short" id="maxpartrepair" name="maxpartrepair" type="text"
							   value="{$site->maxpartrepair}"/>
						<div class="hint">The maximum amount of articles to attempt to repair at a time. If you notice
							that you
							are getting a lot of parts into the missed_parts table, it is possible that you USP is not
							keeping up
							with the requests. Try to reduce the threads to safe scripts or stop using safe scripts
							until improves. Ar least until the cause can be determined.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="partrepairmaxtries">Maximum repair tries:</label></td>
					<td>
						<input class="short" id="partrepairmaxtries" name="partrepairmaxtries" type="text"
							   value="{$site->partrepairmaxtries}"/>
						<div class="hint">Maximum amount of times to try part repair.</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="processjpg">Process JPG:</label></td>
					<td>
						{html_radios id="processjpg" name='processjpg' values=$yesno_ids output=$yesno_names selected=$site->processjpg separator='<br />'}
						<div class="hint">Whether to attempt to retrieve a JPG file while additional post processing,
							these are
							usually on XXX releases.<br/></div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="processthumbnails">Process Video Thumbnails:</label></td>
					<td>
						{html_radios id="processthumbnails" name='processthumbnails' values=$yesno_ids output=$yesno_names selected=$site->processthumbnails separator='<br />'}
						<div class="hint">Whether to attempt to process a video thumbnail image. You must have ffmpeg
							for this.<br/></div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="processvideos">Process Video Samples:</label></td>
					<td>
						{html_radios id="processvideos" name='processvideos' values=$yesno_ids output=$yesno_names selected=$site->processvideos separator='<br />'}
						<div class="hint">Whether to attempt to process a video sample, these videos are very short 1-3
							seconds,
							100KB on average, in ogg video format. You must have ffmpeg for this.<br/></div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="segmentstodownload">Number of Segments to download for
							video/jpg
							samples:</label></td>
					<td>
						<input class="short" id="segmentstodownload" name="segmentstodownload" type="text"
							   value="{$site->segmentstodownload}"/>

						<div class="hint">The maximum number of segments to download to generate the sample video file
							or jpg
							sample image. (Default 2)
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="ffmpeg_duration">Video sample file duration for ffmpeg:</label>
					</td>
					<td>
						<input class="short" id="ffmpeg_duration" name="ffmpeg_duration" type="text"
							   value="{$site->ffmpeg_duration}"/>

						<div class="hint">The maximum duration (In Seconds) for ffmpeg to generate the sample for.
							(Default 5)
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="maxnestedlevels">How many levels deep to go into nested rar/zip
							files:</label></td>
					<td>
						<input class="short" id="maxnestedlevels" name="maxnestedlevels" type="text"
							   value="{$site->maxnestedlevels}"/>
						<div class="hint">If a rar/zip has rar/zip inside of it, how many times should we go in those
							inner rar/zip files.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="innerfileblacklist">Inner file black list Regex:</label></td>
					<td>
						<textarea rows="3" placeholder="Example: /setup\.exe|password\.url/i" id="innerfileblacklist"
								  name="innerfileblacklist">{$site->innerfileblacklist}</textarea>
						<div class="hint">You can add a regex here to set releases to potentially passworded when a file
							name inside a rar/zip matches this regex.
							<br/><strong>You must ensure this regex is valid, a non valid regex will cause errors during
								processing!</strong>
						</div>
					</td>
				</tr>
			</table>
		</fieldset>
		<fieldset id="site_movietrailerset">
			<legend>Movie Trailer settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="trailers_display">Fetch/Display Movie Trailers:</label></td>
					<td>
						{html_radios id="trailers_display" name='trailers_display' values=$yesno_ids output=$yesno_names selected=$site->trailers_display separator='<br />'}
						<div class="hint">Fetch and display trailers from TraktTV (Requires API key) and/or
							TrailerAddict on the details page?
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="trailers_size_x">Trailers width:</label></td>
					<td>
						<input class="short" id="trailers_size_x" name="trailers_size_x" type="text"
							   value="{$site->trailers_size_x}"/>
						<div class="hint">Maximum width in pixels for the trailer window. (Default: 480)</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="trailers_size_y">Trailers height:</label></td>
					<td>
						<input class="short" id="trailers_size_y" name="trailers_size_y" type="text"
							   value="{$site->trailers_size_y}"/>
						<div class="hint">Maximum height in pixels for the trailer window. (Default: 345)</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Advanced - Postprocessing Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="timeoutseconds">Time in seconds to kill
							unrar/7zip/mediainfo/ffmpeg/avconv:</label></td>
					<td>
						<input class="short" id="timeoutseconds" name="timeoutseconds" type="text"
							   value="{$site->timeoutseconds}"/>
						<div class="hint">How much time to wait for unrar/7zip/mediainfo/ffmpeg/avconv before killing
							it, set to 0 to disable.
							60 is a good value. Requires the GNU Timeout path to be set.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="maxaddprocessed">Maximum add PP per run:</label></td>
					<td>
						<input class="short" id="maxaddprocessed" name="maxaddprocessed" type="text"
							   value="{$site->maxaddprocessed}"/>

						<div class="hint">The maximum amount of releases to process for passwords/previews/mediainfo per
							run.
							Every release gets processed here. This uses NNTP an connection, 1 per thread. This does not
							query
							Amazon.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="maxpartsprocessed">Maximum add PP parts downloaded:</label>
					</td>
					<td>
						<input class="short" id="maxpartsprocessed" name="maxpartsprocessed" type="text"
							   value="{$site->maxpartsprocessed}"/>

						<div class="hint">If a part fails to download while post processing, this will retry up to the
							amount
							you set, then give up.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="passchkattempts">Maximum add PP parts checked:</label></td>
					<td>
						<input class="short" id="passchkattempts" name="passchkattempts" type="text"
							   value="{$site->passchkattempts}"/>

						<div class="hint">This overrides the above setting if set above 1. How many parts to check for a
							password before giving up. This slows down post processing massively, better to leave it 1.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="maxrageprocessed">Maximum TVRage per run:</label></td>
					<td>
						<input class="short" id="maxrageprocessed" name="maxrageprocessed" type="text"
							   value="{$site->maxrageprocessed}"/>

						<div class="hint">The maximum amount of TV shows to process with TVRage per run. This does not
							use an
							NNTP connection or query Amazon.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="maximdbprocessed">Maximum movies per run:</label></td>
					<td>
						<input class="short" id="maximdbprocessed" name="maximdbprocessed" type="text"
							   value="{$site->maximdbprocessed}"/>

						<div class="hint">The maximum amount of movies to process with IMDB per run. This does not use
							an NNTP
							connection or query Amazon.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="maxanidbprocessed">Maximum anidb per run:</label></td>
					<td>
						<input class="short" id="maxanidbprocessed" name="maxanidbprocessed" type="text"
							   value="{$site->maxanidbprocessed}"/>

						<div class="hint">The maximum amount of anime to process with anidb per run. This does not use
							an NNTP
							connection or query Amazon.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="maxmusicprocessed">Maximum music per run:</label></td>
					<td>
						<input class="short" id="maxmusicprocessed" name="maxmusicprocessed" type="text"
							   value="{$site->maxmusicprocessed}"/>

						<div class="hint">The maximum amount of music to process with amazon per run. This does not use
							an NNTP
							connection.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="maxgamesprocessed">Maximum games per run:</label></td>
					<td>
						<input class="short" id="maxgamesprocessed" name="maxgamesprocessed" type="text"
							   value="{$site->maxgamesprocessed}"/>

						<div class="hint">The maximum amount of games to process with amazon per run. This does not use
							an NNTP
							connection.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="maxbooksprocessed">Maximum books per run:</label></td>
					<td>
						<input class="short" id="maxbooksprocessed" name="maxbooksprocessed" type="text"
							   value="{$site->maxbooksprocessed}"/>

						<div class="hint">The maximum amount of books to process with amazon per run. This does not use
							an NNTP
							connection
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="maxxxxprocessed">Maximum xxx per run:</label></td>
					<td>
						<input class="short" id="maxxxxprocessed" name="maxxxxprocessed" type="text"
							   value="{$site->maxxxxprocessed}"/>

						<div class="hint">The maximum amount of XXX to process per run. This does not use an NNTP
							connection or query Amazon.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="fixnamesperrun">fixReleaseNames per Run:</label></td>
					<td>
						<input class="short" id="fixnamesperrun" name="fixnamesperrun" type="text"
							   value="{$site->fixnamesperrun}"/>

						<div class="hint">The maximum number of releases to check per run(threaded script only).</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="amazonsleep">Amazon sleep time:</label></td>
					<td>
						<input class="short" id="amazonsleep" name="amazonsleep" type="text"
							   value="{$site->amazonsleep}"/>

						<div class="hint">Sleep time in milliseconds to wait in between amazon requests. If you thread
							post-proc, multiply by the number of threads. ie Postprocessing Threads = 12, Amazon sleep
							time =
							12000<br/><a href="https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html">https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html</a>
						</div>
					</td>
				</tr>

			</table>
		</fieldset>

		<fieldset id="site_nfoprocessing">
			<legend>NFO Processing Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="lookupnfo">Lookup NFO:</label></td>
					<td>
						{html_radios id="lookupnfo" name='lookupnfo' values=$yesno_ids output=$yesno_names selected=$site->lookupnfo separator='<br />'}
						<div class="hint">Whether to attempt to retrieve an nfo file from usenet.<br/>
							<strong>NOTE: disabling nfo lookups will disable movie lookups.</strong>
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="maxnfoprocessed">Maximum NFO files per run:</label></td>
					<td>
						<input class="short" id="maxnfoprocessed" name="maxnfoprocessed" type="text"
							   value="{$site->maxnfoprocessed}"/>
						<div class="hint">The maximum amount of NFO files to process per run. This uses NNTP an
							connection, 1
							per thread. This does not query Amazon.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="maxsizetoprocessnfo">Maximum Release Size to process
							NFOs:</label></td>
					<td>
						<input class="short" id="maxsizetoprocessnfo" name="maxsizetoprocessnfo" type="text"
							   value="{$site->maxsizetoprocessnfo}"/>
						<div class="hint">The maximum size in gigabytes of a release to process it for NFOs. If set to
							0, then ignored.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="minsizetoprocessnfo">Minimum Release Size to process
							NFOs:</label></td>
					<td>
						<input class="short" id="minsizetoprocessnfo" name="minsizetoprocessnfo" type="text"
							   value="{$site->minsizetoprocessnfo}"/>
						<div class="hint">The minimum size in megabytes of a release to process it for NFOs. If set to
							0, then ignored.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="maxnforetries">Maximum amount of times to redownload a
							NFO:</label></td>
					<td>
						<input class="short" id="maxnforetries" name="maxnforetries" type="text"
							   value="{$site->maxnforetries}"/>
						<div class="hint">How many times to retry when a NFO fails to download. If set to 0, we will not
							retry. The max is 7.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Connection Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">

				<tr>
					<td style="width:180px;"><label for="nntpretries">NNTP Retry Attempts:</label></td>
					<td>
						<input class="short" id="nntpretries" name="nntpretries" type="text"
							   value="{$site->nntpretries}"/>

						<div class="hint">The maximum number of retry attmpts to connect to nntp provider. On error,
							each retry
							takes approximately 5 seconds nntp returns reply. (Default 10)
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="delaytime">Delay Time Check:</label></td>
					<td>
						<input class="short" id="delaytime" name="delaytime" type="text" value="{$site->delaytime}"/>
						<div class="hint">The time in hours to wait, since last activity, before releases without parts
							counts
							in the subject are are created<br \> Setting this below 2 hours could create incomplete
							releases..
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="collection_timeout">Collection Timeout Check:</label></td>
					<td>
						<input class="short" id="collection_timeout" name="collection_timeout" type="text"
							   value="{$site->collection_timeout}"/>
						<div class="hint">How many hours to wait before converting a collection into a release that is
							considered "stuck". <br \> Default value is 48 hours.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>
		<fieldset id="site_developerset">
			<legend>Developer Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="showdroppedyencparts">Log Dropped Headers:</label></td>
					<td>
						{html_radios id="showdroppedyencparts" name='showdroppedyencparts' values=$yesno_ids output=$yesno_names selected=$site->showdroppedyencparts separator='<br />'}
						<div class="hint">For developers. Whether to log all headers that have 'yEnc' and are dropped.
							Logged to not_yenc/groupname.dropped.txt.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<fieldset>
			<legend>Advanced - Threaded Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">
				<tr>
					<td style="width:180px;"><label for="binarythreads">Update Binaries Threads:</label></td>
					<td>
						<input class="short" id="binarythreads" name="binarythreads" type="text"
							   value="{$site->binarythreads}"/>

						<div class="hint">The number of threads for update_binaries. If you notice that you are getting
							a lot of
							parts into the missed_parts table, it is possible that you USP is not keeping up with the
							requests.
							Try to reduce the threads. At least until the cause can be determined.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="backfillthreads">Backfill Threads:</label></td>
					<td>
						<input class="short" id="backfillthreads" name="backfillthreads" type="text"
							   value="{$site->backfillthreads}"/>

						<div class="hint">The number of threads for backfill.</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="releasethreads">Update Releases Threads:</label></td>
					<td>
						<input class="short" id="releasethreads" name="releasethreads" type="text"
							   value="{$site->releasethreads}"/>

						<div class="hint">The number of threads for releases update scripts.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="nzbthreads">Import-nzb Threads:</label></td>
					<td>
						<input class="short" id="nzbthreads" name="nzbthreads" type="text" value="{$site->nzbthreads}"/>

						<div class="hint">The number of threads for import-nzb(bulk). This will thread each subfolder.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="postthreads">Postprocessing Additional Threads:</label></td>
					<td>
						<input class="short" id="postthreads" name="postthreads" type="text"
							   value="{$site->postthreads}"/>

						<div class="hint">The number of threads for additional postprocessing. This includes deep rar
							inspection, preview and sample creation and nfo processing.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="nfothreads">NFO Threads:</label></td>
					<td>
						<input class="short" id="nfothreads" name="nfothreads" type="text" value="{$site->nfothreads}"/>
						<div class="hint">The number of threads for nfo postprocessing.
							The max is 16, if you set anything higher it will use 16.
						</div>
					</td>
				</tr>
				<tr>
					<td style="width:180px;"><label for="postthreadsnon">Postprocessing Non-Amazon Threads:</label></td>
					<td>
						<input class="short" id="postthreadsnon" name="postthreadsnon" type="text"
							   value="{$site->postthreadsnon}"/>

						<div class="hint">The number of threads for non-amazon postprocessing. This includes movies,
							anime and
							tv lookups.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:180px;"><label for="fixnamethreads">fixReleaseNames Threads:</label></td>
					<td>
						<input class="short" id="fixnamethreads" name="fixnamethreads" type="text"
							   value="{$site->fixnamethreads}"/>

						<div class="hint">The number of threads for fixReleasesNames. This includes md5, nfos, par2 and
							filenames.
						</div>
					</td>
				</tr>

			</table>
		</fieldset>

		<fieldset>
			<legend>User Settings</legend>
			<table class="input data table table-striped responsive-utilities jambo-table">

				<tr>
					<td style="width:160px;"><label for="registerstatus">Registration Status</label>:</td>
					<td>
						{html_radios id="registerstatus" name='registerstatus' values=$registerstatus_ids output=$registerstatus_names selected=$site->registerstatus separator='<br />'}
						<div class="hint">The status of registrations to the site.</div>
					</td>
				</tr>


				</tr>

				<tr>
					<td style="width:130px;"><label for="userdownloadpurgedays">User Downloads Purge Days</label>:</td>
					<td>
						<input id="userdownloadpurgedays" class="short" name="userdownloadpurgedays" type="text"
							   value="{$site->userdownloadpurgedays}"/>
						<div class="hint">The number of days to preserve user download history, for use when checking
							limits being hit. Set to zero will remove all records of what users download, but retain
							history of when, so that role based limits can still be applied.
						</div>
					</td>
				</tr>

				<tr>
					<td style="width:130px;"><label for="userhostexclusion">IP Whitelist</label>:</td>
					<td>
						<input id="userhostexclusion" class="medium" name="userhostexclusion" type="text"
							   value="{$site->userhostexclusion}"/>
						<div class="hint">A comma separated list of IP addresses which will be excluded from user limits
							on number of requests and downloads per IP address. Include values for google reader and
							other shared services which may be being used.
						</div>
					</td>
				</tr>
			</table>
		</fieldset>

		<input class="btn btn-success" type="submit" value="Save Site Settings"/>

	</form>
</div>

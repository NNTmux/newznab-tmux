<h1>{$page->title}</h1>

<form action="{$SCRIPT_NAME}?action=submit" method="post">

{if $error != ''}
	<div class="error">{$error}</div>
{/if}

<fieldset>
<legend>Main Site Settings, HTML Layout, Tags</legend>
<table class="input">

<tr>
	<td><label for="title">Title</label>:</td>
	<td>
		<input id="title" class="long" name="title" type="text" value="{$fsite->title}" />
		<div class="hint">Displayed around the site and contact form as the name for the site.</div>
	</td>
</tr>

<tr>
	<td><label for="strapline">Strapline</label>:</td>
	<td>
		<input id="strapline" class="long" name="strapline" type="text" value="{$fsite->strapline}" />
		<div class="hint">Displayed in the header on every public page.</div>
	</td>
</tr>

<tr>
	<td><label for="metatitle">Meta Title</label>:</td>
	<td>
		<input id="metatitle" class="long" name="metatitle" type="text" value="{$fsite->metatitle}" />
		<div class="hint">Stem meta-tag appended to all page title tags.</div>
	</td>
</tr>


<tr>
	<td><label for="metadescription">Meta Description</label>:</td>
	<td>
		<textarea class="autosize" id="metadescription" name="metadescription">{$fsite->metadescription}</textarea>
		<div class="hint">Stem meta-description appended to all page meta description tags.</div>
	</td>
</tr>

<tr>
	<td><label for="metakeywords">Meta Keywords</label>:</td>
	<td>
		<textarea class="autosize"  id="metakeywords" name="metakeywords">{$fsite->metakeywords}</textarea>
		<div class="hint">Stem meta-keywords appended to all page meta keyword tags.</div>
	</td>
</tr>

<tr>
	<td><label for="footer">Footer</label>:</td>
	<td>
		<textarea class="autosize"  id="footer" name="footer">{$fsite->footer}</textarea>
		<div class="hint">Displayed in the footer section of every public page.</div>
	</td>
</tr>

<tr>
	<td><label for="style">Default Home Page</label>:</td>
	<td>
		<input id="home_link" class="long" name="home_link" type="text" value="{$fsite->home_link}" />
		<div class="hint">The relative path to a the landing page shown when a user logs in, or clicks the home link.</div>
	</td>
</tr>

<tr>
	<td style="width:160px;"><label for="codename">Code Name</label>:</td>
	<td>
		<input id="codename" name="code" type="text" value="{$fsite->code}" />
		<input type="hidden" name="id" value="{$fsite->id}" />
		<div class="hint">A just for fun value shown in debug and not on public pages.</div>
	</td>
</tr>

<tr>
	<td><label for="style">Theme</label>:</td>
	<td>
		{html_options class="siteeditstyle" id="style" name='style' values=$themelist output=$themelist selected=$fsite->style}
		<div class="hint">The theme folder which will be loaded for css and images and overriden templates.</div>
	</td>
</tr>

<tr>
	<td><label for="style">Dereferrer Link</label>:</td>
	<td>
		<input id="dereferrer_link" class="long" name="dereferrer_link" type="text" value="{$fsite->dereferrer_link}" />
		<div class="hint">Optional URL to prepend to external links</div>
	</td>
</tr>

<tr>
	<td><label for="apienabled">Api Enabled</label>:</td>
	<td>
		{html_radios id="apienabled" name='apienabled' values=$yesno_ids output=$yesno_names selected=$fsite->apienabled separator='<br />'}
		<div class="hint">Whether access to the site via the API is enabled.</div>
	</td>
</tr>

<tr>
	<td style="width:160px;"><label for="showrecentforumposts">Recent Forum Posts</label>:</td>
	<td>
		<input id="showrecentforumposts" name="showrecentforumposts" type="text" value="{$fsite->showrecentforumposts}" />
		<div class="hint">The number of recent forum posts to show in the 'recent forum posts' widget. Set to 0 to disable.</div>
	</td>
</tr>

<tr>
	<td><label for="email">Email</label>:</td>
	<td>
		<input id="email" class="long" name="email" type="text" value="{$fsite->email}" />
		<div class="hint">Shown in the contact us page, and where the contact html form is sent to.</div>
	</td>
</tr>

<tr>
	<td><label for="tandc">Terms and Conditions</label>:</td>
	<td>
		<textarea class="autosize"  id="tandc" name="tandc">{$fsite->tandc}</textarea>
		<div class="hint">Text displayed in the terms and conditions page.</div>
	</td>
</tr>

<tr>
	<td><label for="newznabID">newznab id</label>:</td>
	<td>
		<input id="newznabID" class="long" name="newznabID" type="text" value="{$fsite->newznabID}" />
		<div class="hint">Your registered newznab id. Used for access to additional features.</div>
	</td>
</tr>

</table>
</fieldset>

<fieldset>
<legend>Google Adsense, Analytics and 3rd Party Banners</legend>
<table class="input">
<tr>
	<td style="width:160px;"><label for="google_analytics_acc">Google Analytics</label>:</td>
	<td>
		<input id="google_analytics_acc" name="google_analytics_acc" type="text" value="{$fsite->google_analytics_acc}" />
		<div class="hint">e.g. UA-xxxxxx-x</div>
	</td>
</tr>

<tr>
	<td style="width:160px;"><label for="google_adsense_acc">Google Adsense</label>:</td>
	<td>
		<input id="google_adsense_acc" name="google_adsense_acc" type="text" value="{$fsite->google_adsense_acc}" />
		<div class="hint">e.g. pub-123123123123123</div>
	</td>
</tr>

<tr>
	<td><label for="google_adsense_search">Google Adsense Search</label>:</td>
	<td>
		<input id="google_adsense_search" name="google_adsense_search" type="text" value="{$fsite->google_adsense_search}" />
		<div class="hint">The id of the google search ad panel displayed at the bottom of the left menu.</div>
	</td>
</tr>

<tr>
	<td><label for="adheader">Advert Space in Banner</label>:</td>
	<td>
		<textarea class="autosize"  id="adheader" name="adheader">{$fsite->adheader}</textarea>
		<div class="hint">The banner slot in the header.</div>
	</td>
</tr>

<tr>
	<td><label for="adbrowse">Advert Space in Browse List</label>:</td>
	<td>
		<textarea class="autosize"  id="adbrowse" name="adbrowse">{$fsite->adbrowse}</textarea>
		<div class="hint">The banner slot in the header.</div>
	</td>
</tr>

<tr>
	<td><label for="addetail">Advert Space in Detail View</label>:</td>
	<td>
		<textarea class="autosize"  id="addetail" name="addetail">{$fsite->addetail}</textarea>
		<div class="hint">The banner slot in the release details view.</div>
	</td>
</tr>

</table>
</fieldset>


<fieldset>
<legend>3<sup>rd</sup> Party API Keys</legend>
<table class="input">
<tr>
	<td style="width:160px;"><label for="tmdbkey">TMDB Key</label>:</td>
	<td>
		<input id="tmdbkey" class="long" name="tmdbkey" type="text" value="{$fsite->tmdbkey}" />
		<div class="hint">The api key used for access to tmdb</div>
	</td>
</tr>

<tr>
	<td style="width:160px;"><label for="rottentomatokey">Rotten Tomatoes Key</label>:</td>
	<td>
		<input id="rottentomatokey" class="long" name="rottentomatokey" type="text" value="{$fsite->rottentomatokey}" />
		{html_options style="width:180px;" id="rottentomatoquality" name='rottentomatoquality' values=$rottentomatoquality_ids output=$rottentomatoquality_names selected=$fsite->rottentomatoquality}
		<div class="hint">The api key used for access to rotten tomatoes</div>
	</td>
</tr>

<tr>
	<td><label for="amazonpubkey">Amazon Public Key</label>:</td>
	<td>
		<input id="amazonpubkey" class="long" name="amazonpubkey" type="text" value="{$fsite->amazonpubkey}" />
		<div class="hint">The amazon public api key. Used for cover lookups.</div>
	</td>
</tr>

<tr>
	<td><label for="amazonprivkey">Amazon Private Key</label>:</td>
	<td>
		<input id="amazonprivkey" class="long" name="amazonprivkey" type="text" value="{$fsite->amazonprivkey}" />
		<div class="hint">The amazon private api key. Used for cover lookups.</div>
	</td>
</tr>

<tr>
	<td><label for="amazonassociatetag">Amazon Associate Tag</label>:</td>
	<td>
		<input id="amazonassociatetag" class="long" name="amazonassociatetag" type="text" value="{$fsite->amazonassociatetag}" />
		<div class="hint">The amazon associate tag. Used for cover lookups.</div>
	</td>
</tr>
	<tr>
		<td style="width:180px;"><label for="trakttvkey">Trakt.tv API key:</label></td>
		<td>
			<input id="trakttvkey" class="long" name="trakttvkey" type="text" value="{$fsite->trakttvkey}"/>
			<div class="hint">The trakt.tv api key. Used for movie and tv lookups.</div>
		</td>
	</tr>
	<tr>
		<td style="width:180px;"><label for="fanarttvkey">Fanart.tv API key:</label></td>
		<td>
			<input id="fanarttvkey" class="long" name="fanarttvkey" type="text" value="{$fsite->fanarttvkey}"/>

			<div class="hint">The Fanart.tv api key. Used for Fanart.tv lookups. Fanart.tv would appreciate it if
				you use this service to help them out by adding high quality images not already available on TMDB.
			</div>
		</td>
	</tr>

<tr>
	<td style="width:180px;"><label for="giantbombkey">Giant Bomb API Key:</label></td>
	<td>
		<input id="giantbombkey" class="long" name="giantbombkey" type="text" value="{$fsite->giantbombkey}"/>

		<div class="hint">The giantbomb key. Used for game lookups.</div>
	</td>
</tr>
<tr>
	<td style="width:180px;"><label for="anidbkey">Anidb API key:</label></td>
	<td>
		<input id="anidbkey" class="long" name="anidbkey" type="text" value="{$site->anidbkey}"/>
		<div class="hint">The Anidb api key. Used for Anime lookups.</div>
	</td>
</tr>

</table>
</fieldset>

<fieldset>
<legend>3<sup>rd</sup> Party Application Paths</legend>
<table class="input">
<tr>
	<td style="width:160px;"><label for="unrarpath">Unrar Path</label>:</td>
	<td>
		<input id="unrarpath" class="long" name="unrarpath" type="text" value="{$fsite->unrarpath}" />
		<div class="hint">The path to an unrar binary, used in deep password detection and media info grabbing.
		<br/>Use forward slashes in windows <span style="font-family:courier;">c:/path/to/unrar.exe</span></div>
	</td>
</tr>

<tr>
	<td><label for="tmpunrarpath">Temp Unrar File Path</label>:</td>
	<td>
		<input id="tmpunrarpath" class="long" name="tmpunrarpath" type="text" value="{$fsite->tmpunrarpath}" />
		<div class="hint">The path to where unrar puts files. WARNING: This directory will have its contents deleted.
		<br/>Use forward slashes in windows <span style="font-family:courier;">c:/temp/path/stuff/will/be/unpacked/to</span></div>
	</td>
</tr>

<tr>
	<td><label for="mediainfopath">Mediainfo Path</label>:</td>
	<td>
		<input id="mediainfopath" class="long" name="mediainfopath" type="text" value="{$fsite->mediainfopath}" />
		<div class="hint">The path to the <a href="http://mediainfo.sourceforge.net">mediainfo</a> binary. Used for deep file media analysis. Use empty path to disable mediainfo checks
		<br/>Use forward slashes in windows <span style="font-family:courier;">c:/path/to/mediainfo.exe</span></div>
	</td>
</tr>

<tr>
	<td><label for="ffmpegpath">Ffmpeg Path</label>:</td>
	<td>
		<input id="ffmpegpath" class="long" name="ffmpegpath" type="text" value="{$fsite->ffmpegpath}" />
		<div class="hint">The path to the <a href="http://www.ffmpeg.org/">ffmpeg</a> binary. Used for thumbnailing. Use empty path to disable thumbnailing.
		<br/>Use forward slashes in windows <span style="font-family:courier;">c:/path/to/ffmpeg.exe</span></div>
	</td>
</tr>

<tr>
	<td><label for="lamepath">Lame Path</label>:</td>
	<td>
		<input id="lamepath" class="long" name="lamepath" type="text" value="{$fsite->lamepath}" />
		<div class="hint">The path to the <a href="http://lame.sourceforge.net/download.php">lame</a> binary. Used for audio manipulation.
		<br/>Use forward slashes in windows <span style="font-family:courier;">c:/path/to/lame.exe</span></div>
	</td>
</tr>

	<tr>
		<td style="width:180px;"><label for="zippath">7za Path:</label></td>
		<td>
			<input id="zippath" class="long" name="zippath" type="text" value="{$fsite->zippath}"/>

			<div class="hint">The path to the 7za/p7zip in Ubuntu 13.10 (7zip command line in windows) binary, used
				for grabbing nfos from compressed zip files.
				<br/>Use forward slashes in windows <span style="font-family:courier;">c:/path/to/7z.exe</span>
			</div>
		</td>
	</tr>

</table>
</fieldset>


<fieldset>
	<legend>SABnzbd Integration Settings</legend>
	<table class="input">
		<tr>
			<td style="width:180px;"><label for="sabintegrationtype">Integration Type:</label></td>
			<td>
				{html_radios id="sabintegrationtype" name='sabintegrationtype' values=$sabintegrationtype_ids output=$sabintegrationtype_names selected=$fsite->sabintegrationtype separator='<br />'}
				<div class="hint">
					Whether to allow integration with a SAB install and if so what type of integration.<br/>
					<strong>Setting this to integrated also disables NZBGet from being selectable to the user.</strong><br />
				</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="saburl">SABnzbd Url:</label></td>
			<td>
				<input id="saburl" class="long" name="saburl" type="text" value="{$fsite->saburl}" />
				<div class="hint">The url of the SAB installation, for example: http://localhost:8080/sabnzbd/</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="sabapikey">SABnzbd Api Key:</label></td>
			<td>
				<input id="sabapikey" class="long" name="sabapikey" type="text" value="{$fsite->sabapikey}" />
				<div class="hint">The Api key of the SAB installation. Can be the full api key or the nzb api key (as of SAB 0.6)</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="sabapikeytype">Api Key Type:</label></td>
			<td>
				{html_radios id="sabapikeytype" name='sabapikeytype' values=$sabapikeytype_ids output=$sabapikeytype_names selected=$fsite->sabapikeytype separator='<br />'}
				<div class="hint">Select the type of api key you entered in the above setting</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="sabpriority">Priority Level:</label></td>
			<td>
				{html_options style="width:180px;" id="sabpriority" name='sabpriority' values=$sabpriority_ids output=$sabpriority_names selected=$fsite->sabpriority}
				<div class="hint">Set the priority level for NZBs that are added to your queue</div>
			</td>
		</tr>
		<tr>
			<td><label for="sabcompletedir">SABnzbd Complete Path</label>:</td>
			<td>
				<input id="sabcompletedir" class="long" name="sabcompletedir" type="text" value="{$fsite->sabcompletedir}" />
				<div class="hint">The downloads/complete folder for a local sab install, for example: /var/www/sab/downloads/complete</div>
			</td>
		</tr>
		<tr>
			<td><label for="sabvdir">SABnzbd Complete Vdir URL</label>:</td>
			<td>
				<input id="sabvdir" class="long" name="sabvdir" type="text" value="{$fsite->sabvdir}" />
				<div class="hint">A url to a hosted virtual directory pointing to the sab downloads/complete folder, e.g. http://server/complete</div>
			</td>
		</tr>
	</table>
</fieldset>


<fieldset>
<legend>Usenet Settings</legend>
<table class="input">

<tr>
	<td><label for="nzbpath">Nzb File Path</label>:</td>
	<td>
		<input id="nzbpath" class="long" name="nzbpath" type="text" value="{$fsite->nzbpath}" />
		<div class="hint">The directory where nzb files will be stored.</div>
	</td>
</tr>

<tr>
	<td><label for="rawretentiondays">Header Retention</label>:</td>
	<td>
		<input class="tiny" id="rawretentiondays" name="rawretentiondays" type="text" value="{$fsite->rawretentiondays}" />
		<div class="hint">The number of days binary and part data will be retained for use in raw search and release formation.</div>
	</td>
</tr>

<tr>
	<td><label for="releaseretentiondays">Release Retention</label>:</td>
	<td>
		<input class="tiny" id="releasedays" name="releaseretentiondays" type="text" value="{$fsite->releaseretentiondays}" />
		<div class="hint">The number of days releases will be retained for use throughout site. Set to 0 to disable.</div>
	</td>
</tr>

	<tr>
		<td style="width:180px;"><label for="miscotherretentionhours">Other->Misc Retention Hours:</label></td>
		<td>
			<input class="short" id="miscotherretentionhours" name="miscotherretentionhours" type="text" value="{$site->miscotherretentionhours}"/>
			<div class="hint">The number of hours releases categorized as Misc->Other will be retained. Set to 0 to disable.</div>
		</td>
	</tr>
	<tr>
		<td style="width:180px;"><label for="mischashedretentionhours">Other->Hashed Retention Hours:</label></td>
		<td>
			<input class="short" id="mischashedretentionhours" name="mischashedretentionhours" type="text" value="{$site->mischashedretentionhours}"/>
			<div class="hint">The number of hours releases categorized as Misc->Hashed will be retained. Set to 0 to disable.</div>
		</td>
	</tr>

<tr>
	<td><label for="partsdeletechunks">Parts Delete In Chunks</label>:</td>
	<td>
		<input class="small" id="partsdeletechunks" name="partsdeletechunks" type="text" value="{$fsite->partsdeletechunks}" />
		<div class="hint">Default is 0 (off), which will remove parts in one go. If backfilling or importing and parts table is large, using chunks of 5000+ will speed up removal. Normal indexing is fastest with this setting at 0.</div>
	</td>
</tr>

<tr>
	<td><label for="minfilestoformrelease">Minimum Files to Make a Release</label>:</td>
	<td>
		<input class="tiny" id="minfilestoformrelease" name="minfilestoformrelease" type="text" value="{$fsite->minfilestoformrelease}" />
		<div class="hint">The minimum number of files to make a release. i.e. if set to two, then releases which only contain one file will not be created.</div>
	</td>
</tr>

<tr>
	<td><label for="minsizetoformrelease">Minimum File Size to Make a Release</label>:</td>
	<td>
		<input class="small" id="minsizetoformrelease" name="minsizetoformrelease" type="text" value="{$fsite->minsizetoformrelease}" />
		<div class="hint">The minimum total size in bytes to make a release. If set to 0, then ignored.</div>
	</td>
</tr>

<tr>
	<td><label for="completionpercent">Minimum Completion Percent to Make a Release</label>:</td>
	<td>
		<input class="tiny" id="completionpercent" name="completionpercent" type="text" value="{$fsite->completionpercent}" />
		<div class="hint">The minimum completion percent to make a release. i.e. if set to 97, then releases under 97% completion will not be created. If set to 0, then ignored.</div>
	</td>
</tr>

<tr>
	<td><label for="removespam">Remove Spam</label>:</td>
	<td>
		{html_radios id="removespam" name='removespam' values=$yesno_ids output=$yesno_names selected=$fsite->removespam separator='<br />'}
		<div class="hint">Find and remove spam after each update. An inexact science which uses a few methods to identify virus/spam posts.</div>
	</td>
</tr>

<tr>
	<td><label for="exepermittedcategories">Spam - Accepted Exe Categories</label>:</td>
	<td>
		<input id="exepermittedcategories" class="long" name="exepermittedcategories" type="text" value="{$fsite->exepermittedcategories}" />
		<div class="hint">Which categories to permit .exe files. Requires unrar inner file checking to be enabled.</div>
	</td>
</tr>

<tr>
    <td><label for="updateparsing">Perform Integrated Update Parsing</label>:</td>
    <td>
        {html_radios id="updateparsing" name='updateparsing' values=$yesno_ids output=$yesno_names selected=$fsite->updateparsing separator='<br />'}
        <div class="hint">Update Parsing is a mechanism to decode names using various techniques.</div>
    </td>
</tr>

<tr>
    <td><label for="updatecleanup">Perform Integrated Update Cleanup</label>:</td>
    <td>
        {html_radios id="updatecleanup" name='updatecleanup' values=$yesno_ids output=$yesno_names selected=$fsite->updatecleanup separator='<br />'}
        <div class="hint">Update Cleanup is a general mechanism for deleting and moving unwanted items into misc>other.</div>
    </td>
</tr>

<tr>
    <td><label for="removespecial">Perform Integrated Remove Special Chars</label>:</td>
    <td>
        {html_radios id="removespecial" name='removespecial' values=$yesno_ids output=$yesno_names selected=$fsite->removespecial separator='<br />'}
        <div class="hint">Remove Special is a general mechanism for removing erroneous characters from release names.</div>
    </td>
</tr>

<tr>
	<td><label for="compressedheaders">Use Compressed Headers</label>:</td>
	<td>
		{html_radios id="compressedheaders" name='compressedheaders' values=$yesno_ids output=$yesno_names selected=$fsite->compressedheaders separator='<br />'}
		<div class="hint">Some servers allow headers to be sent over in a compressed format.  If enabled this will use much less bandwidth, but processing times may increase.</div>
	</td>
</tr>


<tr>
	<td><label for="maxmssgs">Max Messages</label>:</td>
	<td>
		<input class="small" id="maxmssgs" name="maxmssgs" type="text" value="{$fsite->maxmssgs}" />
		<div class="hint">The maximum number of messages to fetch at a time from the server.</div>
	</td>
</tr>

<tr>
    <td><label for="maxmsgsperrun">Max Messages Per Run</label>:</td>
    <td>
        <input class="small" id="maxmsgsperrun" name="maxmsgsperrun" type="text" value="{$fsite->maxmsgsperrun}" />
        <div class="hint">The maximum number of messages to process for a group in one run of update_binaries.</div>
    </td>
</tr>

<tr>
	<td><label for="newgroupscanmethod">Where to start new groups</label>:</td>
	<td>
		{html_radios id="newgroupscanmethod" name='newgroupscanmethod' values=$yesno_ids output=$newgroupscan_names selected=$fsite->newgroupscanmethod separator='<br />'}
		<input class="tiny" id="newgroupdaystoscan" name="newgroupdaystoscan" type="text" value="{$fsite->newgroupdaystoscan}" /> Days  or
		<input class="small" id="newgroupmsgstoscan" name="newgroupmsgstoscan" type="text" value="{$fsite->newgroupmsgstoscan}" /> Posts<br />
		<div class="hint">Scan back X (posts/days) for each new group?  Can backfill to scan further.</div>
	</td>
</tr>
	<tr>
		<td style="width:180px;"><label for="safebackfilldate">Safe Backfill Date:</label></td>
		<td>
			<input class="small" id="safebackfilldate" name="safebackfilldate" type="text"
				   value="{$fsite->safebackfilldate}"/>

			<div class="hint">The target date for safe backfill. Format: YYYY-MM-DD</div>
		</td>
	</tr>

</table>
</fieldset>

<fieldset>
<legend>Lookup Settings</legend>
<table class="input">
<tr>
	<td><label for="reqidurl">Allfilled Request Id Lookup URL</label>:</td>
	<td>
		<input class="long" id="reqidurl" name="reqidurl" type="text" value="{$fsite->reqidurl}" />
		<div class="hint">The url to use to translate allfilled style reqid usenet posts into real release titles. Leave blank to not perform lookup.</div>
	</td>
</tr>

<tr>
	<td><label for="reqidurl">Latest Regex Lookup URL</label>:</td>
	<td>
		<input class="long" id="latestregexurl" name="latestregexurl" type="text" value="{$fsite->latestregexurl}" />
		<div class="hint">The url to use to get the latest regexs. Leave blank to not perform lookup. This will retrieve all user contributed regexes.</div>
	</td>
</tr>

<tr>
	<td><label for="lookupnfo">Lookup Nfo</label>:</td>
	<td>
		{html_radios id="lookupnfo" name='lookupnfo' values=$yesno_ids output=$yesno_names selected=$fsite->lookupnfo separator='<br />'}
		<div class="hint">Whether to attempt to retrieve the an nfo file from usenet when processing binaries.<br/><strong>NOTE: disabling nfo lookups will disable movie lookups.</strong></div>
	</td>
</tr>

<tr>
	<td><label for="lookuptvrage">Lookup TV Rage</label>:</td>
	<td>
		{html_radios id="lookuptvrage" name='lookuptvrage' values=$yesno_ids output=$yesno_names selected=$fsite->lookuptvrage separator='<br />'}
		<div class="hint">Whether to attempt to lookup tv rage ids on the web when processing binaries.</div>
	</td>
</tr>

<tr>
	<td><label for="lookupthetvdb">Lookup TheTVDB</label>:</td>
	<td>
		{html_radios id="lookupthetvdb" name='lookupthetvdb' values=$yesno_ids output=$yesno_names selected=$fsite->lookupthetvdb separator='<br />'}
		<div class="hint">Whether to attempt to lookup addidional series information from TheTVDB when processing binaries.</div>
	</td>
</tr>

	<tr>
		<td style="width:180px;"><label for="book_reqids">Type of books to look up:</label></td>
		<td>
			{html_options_multiple id="book_reqids" name='book_reqids' values=$book_reqids_ids output=$book_reqids_names selected=$book_reqids_selected}
			<div class="hint">Categories of Books to lookup information for (only work if Lookup Books is set to yes).</div>
		</td>
	</tr>

<tr>
	<td><label for="lookupimdb">Lookup Movies</label>:</td>
	<td>
		{html_radios id="lookupimdb" name='lookupimdb' values=$yesno_ids output=$yesno_names selected=$fsite->lookupimdb separator='<br />'}
		<div class="hint">Whether to attempt to lookup film information from IMDB or TheMovieDB when processing binaries.</div>
	</td>
</tr>

<tr>
	<td><label for="lookupanidb">Lookup AniDB</label>:</td>
	<td>
		{html_radios id="lookupanidb" name='lookupanidb' values=$yesno_ids output=$yesno_names selected=$fsite->lookupanidb separator='<br />'}
		<div class="hint">Whether to attempt to lookup anime information from AniDB when processing binaries.</div>
	</td>
</tr>

<tr>
	<td><label for="lookupmusic">Lookup Music</label>:</td>
	<td>
		{html_radios id="lookupmusic" name='lookupmusic' values=$yesno_ids output=$yesno_names selected=$fsite->lookupmusic separator='<br />'}
		<div class="hint">Whether to attempt to lookup music information from Amazon when processing binaries.</div>
	</td>
</tr>

<tr>
	<td><label for="lookupgames">Lookup Games</label>:</td>
	<td>
		{html_radios id="lookupgames" name='lookupgames' values=$yesno_ids output=$yesno_names selected=$fsite->lookupgames separator='<br />'}
		<div class="hint">Whether to attempt to lookup game information from Amazon when processing binaries.</div>
	</td>
</tr>

<tr>
	<td><label for="lookupxxx">Lookup XXX</label>:</td>
	<td>
		{html_radios id="lookupxxx" name='lookupxxx' values=$yesno_ids output=$yesno_names selected=$fsite->lookupxxx separator='<br />'}
		<div class="hint">Whether to attempt to lookup XXX information when processing binaries.</div>
	</td>
</tr>

<tr>
	<td style="width:180px;"><label for="maxxxxprocessed">Maximum xxx per run:</label></td>
	<td>
		<input class="short" id="maxxxxprocessed" name="maxxxxprocessed" type="text" value="{$site->maxxxxprocessed}"/>
		<div class="hint">The maximum amount of XXX to process per run. This does not use an NNTP
			connection or query Amazon.
	</div>
	</td>
</tr>

<tr>
	<td><label for="lookupbooks">Lookup Books</label>:</td>
	<td>
		{html_radios id="lookupbooks" name='lookupbooks' values=$yesno_ids output=$yesno_names selected=$fsite->lookupbooks separator='<br />'}
		<div class="hint">Whether to attempt to lookup book information from Amazon when processing binaries.</div>
	</td>
</tr>

<tr>
	<td><label for="lookuplanguage">Movie Lookup Language</label>:</td>
	<td>
		{html_options id="lookuplanguage" name='lookuplanguage' values=$lookuplanguage_iso output=$lookuplanguage_names selected=$fsite->lookuplanguage}
		<div class="hint">Preferred language for scraping external sources.</div>
	</td>
</tr>

<tr>
	<td><label for="saveaudiopreview">Save Audio Preview</label>:</td>
	<td>
		{html_radios id="saveaudiopreview" name='saveaudiopreview' values=$yesno_ids output=$yesno_names selected=$fsite->saveaudiopreview separator='<br />'}
		<div class="hint">Whether to save a preview of an audio release (requires deep rar inspection enabled).<br/>It is advisable to specify a path to the lame binary to reduce the size of audio previews.</div>
	</td>
</tr>

<tr>
	<td style="width:130px;"><label for="audiopreviewprune">Prune Audio Preview</label>:</td>
	<td>
		<input id="audiopreviewprune" class="short" name="audiopreviewprune" type="text" value="{$fsite->audiopreviewprune}" />
		<div class="hint">Delete any audio previews older than this number of days.</div>
	</td>
</tr>

</table>
</fieldset>
<fieldset>
	<legend>Language/Categorization options</legend>
	<table class="input">
		<tr>
			<td style="width:180px;"><label for="categorizeforeign">Categorize Foreign:</label></td>
			<td>
				{html_radios id="categorizeforeign" name='categorizeforeign' values=$yesno_ids output=$yesno_names selected=$fsite->categorizeforeign separator='<br />'}
				<div class="hint">Whether to send foreign movies/tv to
					foreign sections or not. If set to true they will go in foreign categories.
				</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="catwebdl">Categorize WEB-DL:</label></td>
			<td>
				{html_radios id="catwebdl" name='catwebdl' values=$yesno_ids output=$yesno_names selected=$fsite->catwebdl separator='<br />'}
				<div class="hint">Whether to send WEB-DL to the WEB-DL section or not. If set to true they will go in
					WEB-DL category, false will send them in HD TV.<br/>This will also make them inaccessible to
					Sickbeard and possibly Couchpotato.
				</div>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset>
<legend>Password Settings</legend>
<table class="input">
<tr>
	<td><label for="checkpasswordedrar">Check For Passworded Releases</label>:</td>
	<td>
		{html_radios id="checkpasswordedrar" name='checkpasswordedrar' values=$passwd_ids output=$passwd_names selected=$fsite->checkpasswordedrar separator='<br />'}
		<div class="hint">Whether to attempt to peek into every release, to see if rar files are password protected.<br/></div>
	</td>
</tr>

<tr>
	<td><label for="deletepasswordedrelease">Delete Passworded Releases</label>:</td>
	<td>
		{html_radios id="deletepasswordedrelease" name='deletepasswordedrelease' values=$yesno_ids output=$yesno_names selected=$fsite->deletepasswordedrelease separator='<br />'}
		<div class="hint">Whether to delete releases which are passworded or potentially passworded.<br/></div>
	</td>
</tr>

<tr>
	<td><label for="showpasswordedrelease">Show Passworded Releases</label>:</td>
	<td>
		{html_options id="showpasswordedrelease" name='showpasswordedrelease' values=$passworded_ids output=$passworded_names selected=$fsite->showpasswordedrelease}
		<div class="hint">Whether to show passworded or potentially passworded releases in browse, search, api and rss feeds. Potentially passworded means releases which contain .cab or .ace files which are typically password protected.</div>
	</td>
</tr>
</table>
</fieldset>

<fieldset>
<legend>nzpre Settings</legend>
<table class="input">

<tr>
	<td style="width:130px;"><label for="nzpregroup">Group</label>:</td>
	<td>
		<input id="nzpregroup" class="medium" name="nzpregroup" type="text" value="{$fsite->nzpregroup}" />
		<div class="hint">Group to search when looking up nzpre posts.</div>
	</td>
</tr>

<tr>
	<td style="width:130px;"><label for="nzpresubject">Subject</label>:</td>
	<td>
		<input id="nzpresubject" class="long" name="nzpresubject" type="text" value="{$fsite->nzpresubject}" />
		<div class="hint">Subject line. eg. This is an nzpre post!</div>
	</td>
</tr>

<tr>
	<td style="width:130px;"><label for="nzpreposter">Poster</label>:</td>
	<td>
		<input id="nzpreposter" class="medium" name="nzpreposter" type="text" value="{$fsite->nzpreposter}" />
		<div class="hint">Full poster field. eg. tester test@test.com</div>
	</td>
</tr>

<tr>
	<td style="width:130px;"><label for="nzprefield">Field</label>:</td>
	<td>
		<input id="nzprefield" class="medium" name="nzprefield" type="text" value="{$fsite->nzprefield}" />
		<div class="hint">Which header field to parse.</div>
	</td>
</tr>

<tr>
	<td style="width:130px;"><label for="nzprearticles">Articles</label>:</td>
	<td>
		<input id="nzprearticles" class="short" name="nzprearticles" type="text" value="{$fsite->nzprearticles}" />
		<div class="hint">Number of articles to parse when updating.</div>
	</td>
</tr>

<tr>
	<td style="width:130px;"><label for="nzprekey">Key</label>:</td>
	<td>
		<input id="nzprekey" class="long" name="nzprekey" type="text" value="{$fsite->nzprekey}" />
		<div class="hint">Key to decode.</div>
	</td>
</tr>

</table>
</fieldset>

<fieldset>
<legend>Spotnab Settings</legend>
<table class="input">

<tr>
	<td><label for="spotnabpost">Enable Posting</label>:</td>
	<td>
		{html_radios id="spotnabpost" name='spotnabpost' values=$yesno_ids output=$yesno_names selected=$fsite->spotnabpost separator='<br />'}
		<div class="hint">If enabled, encrypted comments from this site will be uploaded using the spotnab protocol. These will be shared with others participating Newznab sites. Set up Spotnab sources <a href="spotnab-list.php">here</a> or use Auto Discovery.</div>
	</td>
</tr>

<tr>
	<td style="width:160px;"><label for="spotnabbroadcast">Broadcast Source</label>:</td>
	<td>
		{html_radios id="spotnabbroadcast" name='spotnabbroadcast' values=$yesno_ids output=$yesno_names selected=$fsite->spotnabbroadcast separator='<br />'}
		<div class="hint">If enabled, a broadcast packet will be uploaded every month, so that other participating Newznab sites can automatically subscribe to your comments.</div>
	</td>
</tr>

<tr>
	<td style="width:160px;"><label for="spotnabdiscover">Auto Discovery</label>:</td>
	<td>
		{html_radios id="spotnabdiscover" name='spotnabdiscover' values=$yesno_ids output=$yesno_names selected=$fsite->spotnabdiscover separator='<br />'}
		<div class="hint">If enabled, other participating Newznab sites will be added automatically (as inactive) to your <a href="spotnab-list.php">sources list</a>.</div>
	</td>
</tr>

<tr>
	<td style="width:160px;"><label for="spotnabprivacy">Enable Privacy</label>:</td>
	<td>
		{html_radios id="spotnabprivacy" name='spotnabprivacy' values=$yesno_ids output=$yesno_names selected=$fsite->spotnabprivacy separator='<br />'}
		<div class="hint">If enabled, uploaded comments use a unique (made up) username.</div>
	</td>
</tr>

<tr>
	<td style="width:160px;"><label for="spotnabautoenable">Source Auto Enable</label>:</td>
	<td>
		{html_radios id="spotnabautoenable" name='spotnabautoenable' values=$yesno_ids output=$yesno_names selected=$fsite->spotnabautoenable separator='<br />'}
		<div class="hint">If enabled, newly discovered Spotnab sources will be enabled by default.</div>
	</td>
</tr>

<tr>
	<td><label for="spotnabuser">Username</label>:</td>
	<td>
		<input name="spotnabuser" type="text" value="{$fsite->spotnabuser}" />
		<div class="hint">The username part used for posting. eg nntp</div>
	</td>
</tr>
<tr>
	<td><label for="spotnabemail">E-mail</label>:</td>
	<td>
		<input name="spotnabemail" type="text" value="{$fsite->spotnabemail}" />
		<div class="hint">The email part used for posting. eg spot@nntp.com</div>
	</td>
</tr>
<tr>
	<td><label for="spotnabgroup">Usenet Group</label>:</td>
	<td>
		<input name="spotnabgroup" type="text" value="{$fsite->spotnabgroup}" />
		<div class="hint">The group where posts are uploaded. eg alt.binaries.backup</div>
	</td>
</tr>

<tr>
  <td style="width:130px;"><label for="spotnabsitepubkey">Public Key</label>:</td>
  <td>
	<textarea class="autosize"  name="spotnabsitepubkey">{$fsite->spotnabsitepubkey|escape:html}</textarea>
	<div class="hint">Public key other sites will use to decrypt comments.</div>
  </td>
</tr>

<tr>
  <td style="width:130px;"><label for="spotnabsiteprvkey">Private Key:<br><u>Do Not Share This</u></label></td>
  <td>
	<textarea class="autosize"  name="spotnabsiteprvkey">{$fsite->spotnabsiteprvkey|escape:html}</textarea>
	<div class="hint">Private key used to encrypt comments before uploading.</div>
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
				{html_options style="width:180px;" class="imdburl" id="imdburl" name='imdburl' values=$imdb_urls output=$imdburl_names selected=$fsite->imdburl}
				<div class="hint">Akas.imdb.com returns titles in their original title, imdb.com returns titles based on
					your IP address (if you are in france, you will get french titles).
				</div>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset>
	<legend>Usenet Settings</legend>
	<table class="input">

		<tr>
			<td style="width:180px;"><label for="maxsizetopostprocess">Maximum Release Size to Post Process:</label></td>
			<td>
				<input class="short" id="maxsizetopostprocess" name="maxsizetopostprocess" type="text"
					   value="{$fsite->maxsizetopostprocess}"/>

				<div class="hint">The maximum size in gigabytes to postprocess a release. If set to 0, then ignored.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="minsizetopostprocess">Minimum Release Size to Post Process:</label></td>
			<td>
				<input class="short" id="minsizetopostprocess" name="minsizetopostprocess" type="text" value="{$site->minsizetopostprocess}"/>
				<div class="hint">The minimum size in megabytes to post process (additional) a release. If set to 0, then ignored.</div>
			</td>
		</tr>
		<tr>

		<tr>
			<td style="width:180px;"><label for="lookuppar2">Lookup PAR2:</label></td>
			<td>
				{html_radios id="lookuppar2" name='lookuppar2' values=$yesno_ids output=$yesno_names selected=$fsite->lookuppar2 separator='<br />'}
				<div class="hint">Whether to attempt to find a better name for releases in misc->other using the PAR2
					file.<br/><strong>NOTE: this can be slow depending on the group!</strong></div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="addpar2">Add PAR2 contents to file contents:</label></td>
			<td>
				{html_radios id="addpar2" name='addpar2' values=$yesno_ids output=$yesno_names selected=$fsite->addpar2 separator='<br />'}
				<div class="hint">When going through PAR2 files, add them to the RAR file content list of the NZB.</div>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset>
	<legend>Advanced Settings - For advanced users</legend>
	<table class="input">
		<tr>
			<td style="width:180px;"><label for="maxnzbsprocessed">Maximum NZBs processed:</label></td>
			<td>
				<input class="short" id="maxnzbsprocessed" name="maxnzbsprocessed" type="text"
					   value="{$fsite->maxnzbsprocessed}"/>

				<div class="hint">The maximum amount of NZB files to create in update_releases.</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxpartrepair">Maximum repair per run:</label></td>
			<td>
				<input class="short" id="maxpartrepair" name="maxpartrepair" type="text"
					   value="{$fsite->maxpartrepair}"/>

				<div class="hint">The maximum amount of articles to attempt to repair at a time. If you notice that you
					are getting a lot of parts into the partrepair table, it is possible that you USP is not keeping up
					with the requests. Try to reduce the threads to safe scripts, stop using safe scripts or stop using
					nntpproxy until improves. Ar least until the cause can be determined.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="partrepair">Part Repair:</label></td>
			<td>
				{html_radios id="partrepair" name='partrepair' values=$yesno_ids output=$yesno_names selected=$fsite->partrepair separator='<br />'}
				<div class="hint">Whether to attempt to repair parts or not, increases backfill/binaries updating
					time.
				</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="safepartrepair">Part Repair for Backfill Scripts:</label></td>
			<td>
				{html_radios id="safepartrepair" name='safepartrepair' values=$yesno_ids output=$yesno_names selected=$site->safepartrepair separator='<br />'}
				<div class="hint">Whether to put unreceived parts into partrepair table when running binaries(safe) or backfill scripts.</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="maxpartrepair">Maximum repair per run:</label></td>
			<td>
				<input class="short" id="maxpartrepair" name="maxpartrepair" type="text" value="{$site->maxpartrepair}"/>
				<div class="hint">The maximum amount of articles to attempt to repair at a time. If you notice that you
					are getting a lot of parts into the partrepair table, it is possible that you USP is not keeping up
					with the requests. Try to reduce the threads to safe scripts, stop using safe scripts or stop using
					nntpproxy until improves. Ar least until the cause can be determined.
				</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="partrepairmaxtries">Maximum repair tries:</label></td>
			<td>
				<input class="short" id="partrepairmaxtries" name="partrepairmaxtries" type="text" value="{$site->partrepairmaxtries}"/>
				<div class="hint">Maximum amount of times to try part repair.</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="yydecoderpath">yEnc Type:</label></td>
			<td>
				<input id="yydecoderpath" class="long" name="yydecoderpath" type="text"
					   value="{$fsite->yydecoderpath}"/>

				<div class="hint">
					Leaving this empty will use PHP to decode yEnc, which is slow.
					<br/>Putting the path to yydecode will use yydecode, which is faster than PHP. <a
							style="color:#0082E1"
							href="http://sourceforge.net/projects/yydecode/files/yydecode/0.2.10/">Download yydecode
						on sourceforce.</a>
					<br/>Putting in <strong style="color:#ac2925">simple_php_yenc_decode</strong> will use that
					extension which is even faster <strong>(you must have the extension)</strong>. <a
							style="color:#0082E1" href="https://github.com/kevinlekiller/simple_php_yenc_decode">View
						simple_php_yenc_decode on github.</a>
				</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="processjpg">Process JPG:</label></td>
			<td>
				{html_radios id="processjpg" name='processjpg' values=$yesno_ids output=$yesno_names selected=$fsite->processjpg separator='<br />'}
				<div class="hint">Whether to attempt to retrieve a JPG file while additional post processing, these are
					usually on XXX releases.<br/></div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="processvideos">Process Video Samples:</label></td>
			<td>
				{html_radios id="processvideos" name='processvideos' values=$yesno_ids output=$yesno_names selected=$fsite->processvideos separator='<br />'}
				<div class="hint">Whether to attempt to process a video sample, these videos are very short 1-3 seconds,
					100KB on average, in ogv format. You must have ffmpeg for this.<br/></div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="segmentstodownload">Number of Segments to download for video/jpg
					samples:</label></td>
			<td>
				<input class="short" id="segmentstodownload" name="segmentstodownload" type="text"
					   value="{$fsite->segmentstodownload}"/>

				<div class="hint">The maximum number of segments to download to generate the sample video file or jpg
					sample image. (Default 2)
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="ffmpeg_duration">Video sample file duration for ffmpeg:</label></td>
			<td>
				<input class="short" id="ffmpeg_duration" name="ffmpeg_duration" type="text"
					   value="{$fsite->ffmpeg_duration}"/>

				<div class="hint">The maximum duration (In Seconds) for ffmpeg to generate the sample for. (Default 5)
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="extractusingrarinfo">Extract RAR/ZIP using rarinfo:</label></td>
			<td>
				{html_radios id="extractusingrarinfo" name='extractusingrarinfo' values=$yesno_ids output=$yesno_names selected=$site->extractusingrarinfo separator='<br />'}
				<div class="hint">Whether to use rarinfo or 7zip/unrar directly to decompress zip/rar files.
					<br /><strong>Using rarinfo is faster, but the extracted files are not as large which leads to smaller video samples/ potentially black preview screenshots.</strong>
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxnestedlevels">How many levels deep to go into nested rar/zip files:</label></td>
			<td>
				<input class="short" id="maxnestedlevels" name="maxnestedlevels" type="text" value="{$site->maxnestedlevels}"/>
				<div class="hint">If a rar/zip has rar/zip inside of it, how many times should we go in those inner rar/zip files.</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="innerfileblacklist">Inner file black list Regex:</label></td>
			<td>
				<textarea rows="3" placeholder="Example: /setup\.exe|password\.url/i" id="innerfileblacklist" name="innerfileblacklist">{$site->innerfileblacklist}</textarea>
				<div class="hint">You can add a regex here to set releases to potentially passworded when a file name inside a rar/zip matches this regex.
					<br /><strong>You must ensure this regex is valid, a non valid regex will cause errors during processing!</strong>
				</div>
			</td>
		</tr>
	</table>
</fieldset>

<fieldset id="site_requidset">
	<legend>RequestID Settings</legend>
	<table class="input">
		<tr>
			<td style="width:180px;"><label for="lookup_reqids">Lookup Request IDs:</label></td>
			<td>
				{html_options style="width:180px;" id="lookup_reqids" name='lookup_reqids' values=$lookup_reqids_ids output=$lookup_reqids_names selected=$site->lookup_reqids}
				<div class="hint">Whether to attempt to lookup Request IDs using the Request ID link below. This will rename your releases to proper PreDB names.</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="request_url">Request ID Link:</label></td>
			<td>
				<input id="request_url" class="long" name="request_url" type="text" value="{$site->request_url}"/>
				<div class="hint">Optional URL to lookup Request IDs. [REQUEST_ID] gets replaced with the request ID from the
					post. [GROUP_NM] Gets replaced with the group name.
				</div>
			</td>
		</tr>
		<tr>
			<td style="width:180px;"><label for="request_hours">Max hours to recheck Request IDs:</label></td>
			<td>
				<input id="request_hours" class="short" name="request_hours" type="text" value="{$site->request_hours}"/>
				<div class="hint">The maximum hours after a release is added to recheck for a Request ID match.</div>
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
				<input class="short" id="maxaddprocessed" name="maxaddprocessed" type="text"
					   value="{$fsite->maxaddprocessed}"/>

				<div class="hint">The maximum amount of releases to process for passwords/previews/mediainfo per run.
					Every release gets processed here. This uses NNTP an connection, 1 per thread. This does not query
					Amazon.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxpartsprocessed">Maximum add PP parts downloaded:</label></td>
			<td>
				<input class="short" id="maxpartsprocessed" name="maxpartsprocessed" type="text"
					   value="{$fsite->maxpartsprocessed}"/>

				<div class="hint">If a part fails to download while post processing, this will retry up to the amount
					you set, then give up.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="passchkattempts">Maximum add PP parts checked:</label></td>
			<td>
				<input class="short" id="passchkattempts" name="passchkattempts" type="text"
					   value="{$fsite->passchkattempts}"/>

				<div class="hint">This overrides the above setting if set above 1. How many parts to check for a
					password before giving up. This slows down post processing massively, better to leave it 1.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxnfoprocessed">Maximum NFO files per run:</label></td>
			<td>
				<input class="short" id="maxnfoprocessed" name="maxnfoprocessed" type="text"
					   value="{$fsite->maxnfoprocessed}"/>

				<div class="hint">The maximum amount of NFO files to process per run. This uses NNTP an connection, 1
					per thread. This does not query Amazon.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxrageprocessed">Maximum TVRage per run:</label></td>
			<td>
				<input class="short" id="maxrageprocessed" name="maxrageprocessed" type="text"
					   value="{$fsite->maxrageprocessed}"/>

				<div class="hint">The maximum amount of TV shows to process with TVRage per run. This does not use an
					NNTP connection or query Amazon.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maximdbprocessed">Maximum movies per run:</label></td>
			<td>
				<input class="short" id="maximdbprocessed" name="maximdbprocessed" type="text"
					   value="{$fsite->maximdbprocessed}"/>

				<div class="hint">The maximum amount of movies to process with IMDB per run. This does not use an NNTP
					connection or query Amazon.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxanidbprocessed">Maximum anidb per run:</label></td>
			<td>
				<input class="short" id="maxanidbprocessed" name="maxanidbprocessed" type="text"
					   value="{$fsite->maxanidbprocessed}"/>

				<div class="hint">The maximum amount of anime to process with anidb per run. This does not use an NNTP
					connection or query Amazon.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxmusicprocessed">Maximum music per run:</label></td>
			<td>
				<input class="short" id="maxmusicprocessed" name="maxmusicprocessed" type="text"
					   value="{$fsite->maxmusicprocessed}"/>

				<div class="hint">The maximum amount of music to process with amazon per run. This does not use an NNTP
					connection.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxgamesprocessed">Maximum games per run:</label></td>
			<td>
				<input class="short" id="maxgamesprocessed" name="maxgamesprocessed" type="text"
					   value="{$fsite->maxgamesprocessed}"/>

				<div class="hint">The maximum amount of games to process with amazon per run. This does not use an NNTP
					connection.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="maxbooksprocessed">Maximum books per run:</label></td>
			<td>
				<input class="short" id="maxbooksprocessed" name="maxbooksprocessed" type="text"
					   value="{$fsite->maxbooksprocessed}"/>

				<div class="hint">The maximum amount of books to process with amazon per run. This does not use an NNTP
					connection
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="fixnamesperrun">fixReleaseNames per Run:</label></td>
			<td>
				<input class="short" id="fixnamesperrun" name="fixnamesperrun" type="text"
					   value="{$fsite->fixnamesperrun}"/>

				<div class="hint">The maximum number of releases to check per run(threaded script only).</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="amazonsleep">Amazon sleep time:</label></td>
			<td>
				<input class="short" id="amazonsleep" name="amazonsleep" type="text" value="{$fsite->amazonsleep}"/>

				<div class="hint">Sleep time in milliseconds to wait in between amazon requests. If you thread
					post-proc, multiply by the number of threads. ie Postprocessing Threads = 12, Amazon sleep time =
					12000<br/><a href="https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html">https://affiliate-program.amazon.com/gp/advertising/api/detail/faq.html</a>
				</div>
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
				<input class="short" id="nntpretries" name="nntpretries" type="text" value="{$fsite->nntpretries}"/>

				<div class="hint">The maximum number of retry attmpts to connect to nntp provider. On error, each retry
					takes approximately 5 seconds nntp returns reply. (Default 10)
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="nntpproxy">Use NNTP Proxy:</label></td>
			<td>
				{html_radios id="nntpproxy" name='nntpproxy' values=$yesno_ids output=$yesno_names selected=$site->nntpproxy separator='<br />'}
				<div class="hint">
					<strong>nntpproxy does not support the POST usenet command which is required for uploading usenet comments when using sharing.</strong>
					Using the NNTP Proxy for newznab can improve performance of newznab. It uses
					connection pooling which not only give more control over the number of connections to use but also
					reduces time for connection setup/teardown. The proxy also takes care of compressed headers for you.
					To use this featrure you will need to install socketpool (sudo pip install socketpool or sudo easy_install socketpool)
					(ensure python2 is default) and edit the configuration file (nntpproxy.conf and nntpproxy_a.conf) in the
					update_scripts/nix_scripts/tmux/python/lib (copy sample) directory and finally edit your www/config.php file
					to use the proxy (username and password are ignored by the proxy - make then anything you like - the
					proxy doesn't use ssl either). Make sure you turn off the use compressed headers option here in site
					preferences (the proxy uses compressed headers by default and passes on decompressed data).
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="alternate_nntp">Alternate NNTP Provider:</label></td>
			<td>
				{html_radios id="alternate_nntp" name='alternate_nntp' values=$yesno_ids output=$yesno_names selected=$site->alternate_nntp separator='<br />'}
				<div class="hint">This sets Postproccessing Additional/Nfo to use the alternate NNTP provider as set in
					config.php.
				</div>
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
				<input class="short" id="binarythreads" name="binarythreads" type="text"
					   value="{$fsite->binarythreads}"/>

				<div class="hint">The number of threads for update_binaries. If you notice that you are getting a lot of
					parts into the partrepair table, it is possible that you USP is not keeping up with the requests.
					Try to reduce the threads. At least until the cause can be determined.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="backfillthreads">Backfill Threads:</label></td>
			<td>
				<input class="short" id="backfillthreads" name="backfillthreads" type="text"
					   value="{$fsite->backfillthreads}"/>

				<div class="hint">The number of threads for backfill.</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="releasesthreads">Update Releases Threads:</label></td>
			<td>
				<input class="short" id="releasethreads" name="releasethreads" type="text"
					   value="{$fsite->releasethreads}"/>

				<div class="hint">The number of threads for update_releases. This actualy makes no difference, newznab
					update_releases is not threaded.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="nzbthreads">Import-nzb Threads:</label></td>
			<td>
				<input class="short" id="nzbthreads" name="nzbthreads" type="text" value="{$fsite->nzbthreads}"/>

				<div class="hint">The number of threads for import-nzb(bulk). This will thread each subfolder.</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="postthreads">Postprocessing Additional Threads:</label></td>
			<td>
				<input class="short" id="postthreads" name="postthreads" type="text" value="{$fsite->postthreads}"/>

				<div class="hint">The number of threads for additional postprocessing. This includes deep rar
					inspection, preview and sample creation and nfo processing.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="postthreadsnon">Postprocessing Non-Amazon Threads:</label></td>
			<td>
				<input class="short" id="postthreadsnon" name="postthreadsnon" type="text"
					   value="{$fsite->postthreadsnon}"/>

				<div class="hint">The number of threads for non-amazon postprocessing. This includes movies, anime and
					tv lookups.
				</div>
			</td>
		</tr>

		<tr>
			<td style="width:180px;"><label for="fixnamethreads">fixReleaseNames Threads:</label></td>
			<td>
				<input class="short" id="fixnamethreads" name="fixnamethreads" type="text"
					   value="{$fsite->fixnamethreads}"/>

				<div class="hint">The number of threads for fixReleasesNames. This includes md5, nfos, par2 and
					filenames.
				</div>
			</td>
		</tr>

	</table>
</fieldset>

<fieldset>
<legend>User Settings</legend>
<table class="input">

<tr>
	<td style="width:160px;"><label for="registerstatus">Registration Status</label>:</td>
	<td>
		{html_radios id="registerstatus" name='registerstatus' values=$registerstatus_ids output=$registerstatus_names selected=$fsite->registerstatus separator='<br />'}
		<div class="hint">The status of registrations to the site.</div>
	</td>
</tr>

<tr>
	<td><label for="storeuserips">Store User Ip</label>:</td>
	<td>
		{html_radios id="storeuserips" name='storeuserips' values=$yesno_ids output=$yesno_names selected=$fsite->storeuserips separator='<br />'}
		<div class="hint">Whether to store the users ip address when they signup or login.</div>
	</td>
</tr>

<tr>
  <td style="width:130px;"><label for="userdownloadpurgedays">User Downloads Purge Days</label>:</td>
  <td>
    <input id="userdownloadpurgedays" class="short" name="userdownloadpurgedays" type="text" value="{$fsite->userdownloadpurgedays}" />
    <div class="hint">The number of days to preserve user download history, for use when checking limits being hit. Set to zero will remove all records of what users download, but retain history of when, so that role based limits can still be applied.</div>
  </td>
</tr>

<tr>
  <td style="width:130px;"><label for="userhostexclusion">IP Whitelist</label>:</td>
  <td>
    <input id="userhostexclusion" class="medium" name="userhostexclusion" type="text" value="{$fsite->userhostexclusion}" />
    <div class="hint">A comma separated list of IP addresses which will be excluded from user limits on number of requests and downloads per IP address. Include values for google reader and other shared services which may be being used.</div>
  </td>
</tr>
</table>
</fieldset>

<fieldset>
<legend>Recaptcha</legend>
<table class="input">

<tr>
  <td style="width:130px;"><label for="recaptchapublickey">ReCaptcha Public Key</label>:</td>
  <td>
    <input id="recaptchapublickey" class="medium" name="recaptchapublickey" type="text" value="{$fsite->recaptchapublickey}" />
    <div class="hint">The public key supplied by recaptcha.net. Sign up for recaptcha at <a href="https://www.google.com/recaptcha/admin/create">https://www.google.com/recaptcha/admin/create</a></div>
  </td>
</tr>

<tr>
  <td style="width:130px;"><label for="recaptchaprivatekey">ReCaptcha Private Key</label>:</td>
  <td>
    <input id="recaptchaprivatekey" class="medium" name="recaptchaprivatekey" type="text" value="{$fsite->recaptchaprivatekey}" />
    <div class="hint">The private key supplied by recaptcha.net</div>
  </td>
</tr>



<tr>
	<td><label for="registerrecaptcha">Register Recaptcha</label>:</td>
	<td>
		{html_radios id="registerrecaptcha" name='registerrecaptcha' values=$yesno_ids output=$yesno_names selected=$fsite->registerrecaptcha separator='<br />'}
		<div class="hint">Whether to display a ReCaptcha captcha for registering.</div>
	</td>
</tr>

</table>
</fieldset>

<input type="submit" value="Save Site Settings" />

</form>
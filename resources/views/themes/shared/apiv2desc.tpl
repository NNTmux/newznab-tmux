<div class="well well-sm">
    <h3>{$title}</h3>
    <p>Here lives the documentation for the api v2 for accessing nzb and index data. Api functions can be called by providing an api token.</p>
    <br>
    {if $loggedin=="true"}
        <h3>API Credentials</h3>
        <p>Your credentials should be provided as <span style="font-family:courier;">&api_token={$userdata.api_token}</span>
        </p>
    {/if}
    <br>
    <h3>Available Functions</h3>
    <dl>
        <dt>Capabilities <span style="font-family:courier;"><a
                    href="{$smarty.const.WWW_TOP}/api/v2/capabilities">capabilities</a></span></dt>
        <dd>Reports the capabilities of the server. Includes information about the server name, available search
            categories and version number of the nntmux being used.<br>Capabilities do not require any
            credentials in order to be retrieved.
        </dd>
        <br>
        <dt>Search <span style="font-family:courier;"><a href="{$smarty.const.WWW_TOP}/api/v2/search?id=linux&amp;api_token={$userdata.api_token}">search?id=linux</a></span>
        </dt>
        <dd>Returns a list of nzbs matching a query. You can also filter by site category by including a comma separated
            list of categories as follows <span style="font-family:courier;"><a
                    href="{$smarty.const.WWW_TOP}/api/v2/search?cat={$catClass::GAME_ROOT},{$catClass::MOVIE_ROOT}&amp;api_token={$userdata.api_token}">search?cat={$catClass::GAME_ROOT}
                    ,{$catClass::MOVIE_ROOT}</a></span>.
        </dd>
        <br>
        <dt>TV <span style="font-family:courier;"><a
                    href="{$smarty.const.WWW_TOP}/api/v2/tv?id=law%20and%20order&amp;season=7&amp;ep=12&amp;api_token={$userdata.api_token}">tv?id=law and order&amp;season=7&amp;ep=12</a></span>
        </dt>
        <dd>Returns a list of nzbs matching a query, category, tvrageid, season or episode.
            You can also filter by site category by including a comma separated list of categories as follows:
            <span style="font-family:courier;"><a
                    href="{$smarty.const.WWW_TOP}/api/v2/tv?rid=2204&amp;cat={$catClass::GAME_ROOT},{$catClass::MOVIE_ROOT}&amp;api_token={$userdata.api_token}">tv?cat={$catClass::GAME_ROOT}
                    ,{$catClass::MOVIE_ROOT}</a></span>.
        </dd>
        <dd>
            You can also supply the following parameters to do site specfic ID searches:
            &amp;rid=25056 (TVRage) &amp;tvdbid=153021 (TVDB) &amp;traktid=1393 (Trakt) &amp;tvmazeid=73 (TVMaze) &amp;imdbid=1520211
            (IMDB) &amp;tmdbid=1402 (TMDB).
        </dd>
        <br>
        <dt>Movies <span style="font-family:courier;"><a
                    href="{$smarty.const.WWW_TOP}/api/v2/movies?imdbid=1418646&amp;api_token={$userdata.api_token}">movies?imdbid=1418646</a></span>
        </dt>
        <dd>Returns a list of nzbs matching a query, an imdbid and optionally a category. Filter by
            site category by including a comma separated list of categories as follows <span
                style="font-family:courier;"><a
                    href="{$smarty.const.WWW_TOP}/api/v2/movies?imdbid=1418646&amp;cat={$catClass::MOVIE_SD},{$catClass::MOVIE_HD}&amp;api_token={$userdata.api_token}">movies?imdbid=1418646&amp;cat={$catClass::MOVIE_SD}
                    ,{$catClass::MOVIE_HD}</a></span>.
        </dd>
        <br>
        <dt>Details <span style="font-family:courier;"><a
                    href="{$smarty.const.WWW_TOP}/api/v2/details?id=9ca52909ba9b9e5e6758d815fef4ecda&amp;api_token={$userdata.api_token}">details?id=9ca52909ba9b9e5e6758d815fef4ecda</a></span>
        </dt>
        <dd>Returns detailed information about an nzb.</dd>
        <br>
        <dt>Get <span style="font-family:courier;"><a
                    href="{$smarty.const.WWW_TOP}/api/v2/getnzb?id=9ca52909ba9b9e5e6758d815fef4ecda&amp;api_token={$userdata.api_token}">getnzb?id=9ca52909ba9b9e5e6758d815fef4ecda</a></span>
        </dt>
        <dd>Downloads the nzb file associated with an Id.</dd>
    </dl>
    <br>
    <h3>Output Format</h3>
    <p>All information is returned in JSON format</p>
</div>

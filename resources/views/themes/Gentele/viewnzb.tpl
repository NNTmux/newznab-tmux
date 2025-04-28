<div class="header">
    <h2>NZB > <strong>Details</strong></h2>
    <div class="breadcrumb-wrapper">
        <ol class="breadcrumb">
            <li><a href="{{url("{$site->home_link}")}}">Home</a></li>
            / NZB
        </ol>
    </div>
</div>
<div class="row">
    <div class="col-lg-12 col-sm-12 col-12">
        <div class="card card-default">
            <div class="card-body">
                <h1>{$release.searchname|escape:"htmlall"}
                    {if !empty($failed)}
                    <span class="btn btn-light btn-xs" title="This release has failed to download for some users">
                        <i class="fa fa-thumbs-o-up"></i> {$release.grabs} Grab{if $release.grabs != 1}s{/if} /
                        <i class="fa fa-thumbs-o-down"></i> {$failed} Failed Download{if $failed != 1}s{/if}
                    </span>
                    {/if}
                </h1>

                <!-- Admin/Mod Actions -->
                        <div class="mb-3">
                            {if $isadmin == true || $ismod == true}
                                <a class="badge bg-warning text-decoration-none" href="{{url("/admin/release-edit?id={$release.guid}")}}" title="Edit release">
                                    <i class="fa fa-edit me-1"></i>Edit
                                </a>
                            {/if}
                            {if isset($isadmin)}
                                <a class="badge bg-danger text-decoration-none" href="{{url("/admin/release-delete/{$release.guid}")}}" title="Delete release">
                                    <i class="fa fa-trash me-1"></i>Delete
                                </a>
                            {/if}
                        </div>

                        <!-- Media Links -->
                        <div class="media-links mb-3">
                            {if $movie && $release.videos_id <= 0}
                                {if $movie.imdbid > 0}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}http://www.imdb.com/title/tt{$movie.imdbid}/" title="View at IMDB">
                                        <i class="fa fa-film me-1"></i>IMDB
                                    </a>
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}http://trakt.tv/search/imdb/tt{$movie.imdbid}/" name="trakt{$release.imdbid}" title="View Trakt page" rel="trakt">
                                        <i class="fa fa-tv me-1"></i>TRAKT
                                    </a>
                                {/if}
                                {if $movie.tmdbid > 0}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}http://www.themoviedb.org/movie/{$movie.tmdbid}" title="View at TMDb">
                                        <i class="fa fa-database me-1"></i>TMDb
                                    </a>
                                {/if}
                                {if $movie.imdbid > 0}
                                    <a class="badge bg-info text-decoration-none me-1" href="{{url("/Movies?imdb={$movie.imdbid}")}}" title="View all versions">
                                        <i class="fa fa-list-alt me-1"></i>Movie View
                                    </a>
                                {/if}
                            {/if}

                            {if $anidb && $release.anidbid > 0}
                                <a class="badge bg-info text-decoration-none me-1" href="{{url("/anime?id={$release.anidbid}")}}" title="View all releases from this anime">
                                    <i class="fa fa-list me-1"></i>View all episodes
                                </a>
                                <a class="badge bg-info text-decoration-none me-1" href="{$site->dereferrer_link}http://anidb.net/perl-bin/animedb.pl?show=anime&aid={$anidb.anidbid}" title="View at AniDB" target="_blank">
                                    <i class="fa fa-external-link-alt me-1"></i>AniDB
                                </a>
                                <a class="badge bg-info text-decoration-none me-1" href="{{url("/rss/full-feed?anidb={$release.anidbid}&amp;dl=1&amp;i={$userdata.id}&amp;api_token={$userdata.api_token}")}}">
                                    <i class="fa fa-rss me-1"></i>Anime RSS Feed
                                </a>
                            {/if}

                            {if $show && $release.videos_id > 0}
                                <a href="{{url("/myshows?action=add&id={$release.videos_id}&from={$smarty.server.REQUEST_URI|escape:"url"}")}}" class="badge bg-success text-decoration-none me-1">
                                    <i class="fa fa-plus-circle me-1"></i>Add to My Shows
                                </a>
                                <a class="badge bg-info text-decoration-none me-1" href="{{url("/series/{$release.videos_id}")}}" title="View all releases for this series">
                                    <i class="fa fa-list me-1"></i>View all episodes
                                </a>
                                {if $show.tvdb > 0}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}http://thetvdb.com/?tab=series&id={$show.tvdb}")"}}" title="View at TheTVDB">
                                        <i class="fa fa-tv me-1"></i>TheTVDB
                                    </a>
                                {/if}
                                {if $show.tvmaze > 0}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}http://tvmaze.com/shows/{$show.tvmaze}" title="View at TVMaze">
                                        <i class="fa fa-tv me-1"></i>TVMaze
                                    </a>
                                {/if}
                                {if $show.trakt > 0}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}http://www.trakt.tv/shows/{$show.trakt}" title="View at TraktTv">
                                        <i class="fa fa-tv me-1"></i>Trakt
                                    </a>
                                {/if}
                                {if $show.tvrage > 0}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}http://www.tvrage.com/shows/id-{$show.tvrage}" title="View at TV Rage">
                                        <i class="fa fa-tv me-1"></i>TV Rage
                                    </a>
                                {/if}
                                {if $show.tmdb > 0}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}https://www.themoviedb.org/tv/{$show.tmdb}" title="View at TheMovieDB">
                                        <i class="fa fa-database me-1"></i>TMDb
                                    </a>
                                {/if}
                            {/if}

                            <!-- External Links -->
                            {if $con && $con.url != ""}
                                <a href="{$site->dereferrer_link}{$con.url}/" class="badge bg-info text-decoration-none me-1" target="_blank">
                                    <i class="fa fa-shopping-cart me-1"></i>Amazon
                                </a>
                            {/if}
                            {if $book && $book.url != ""}
                                <a href="{$site->dereferrer_link}{$book.url}/" class="badge bg-info text-decoration-none me-1" target="_blank">
                                    <i class="fa fa-book me-1"></i>Amazon
                                </a>
                            {/if}
                            {if $music && $music.url != ""}
                                <a href="{$site->dereferrer_link}{$music.url}/" class="badge bg-info text-decoration-none me-1" target="_blank">
                                    <i class="fa fa-music me-1"></i>Amazon
                                </a>
                            {/if}

                            <!-- XXX Links -->
                            {if $xxx}
                                {if $xxx.classused === "ade"}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}" title="View at Adult DVD Empire">
                                        <i class="fa fa-external-link-alt me-1"></i>ADE
                                    </a>
                                {elseif $xxx.classused === "adm"}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}" title="View at Adult DVD Marketplace">
                                        <i class="fa fa-external-link-alt me-1"></i>ADM
                                    </a>
                                {elseif $xxx.classused === "pop"}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}" title="View at Popporn">
                                        <i class="fa fa-external-link-alt me-1"></i>PopPorn
                                    </a>
                                {elseif $xxx.classused === "aebn"}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}" title="View at Adult Entertainment Broadcast Network">
                                        <i class="fa fa-external-link-alt me-1"></i>AEBN
                                    </a>
                                {elseif $xxx.classused === "hm"}
                                    <a class="badge bg-info text-decoration-none me-1" target="_blank" href="{$site->dereferrer_link}{$xxx.directurl}" title="View at Hot Movies">
                                        <i class="fa fa-external-link-alt me-1"></i>HotMovies
                                    </a>
                                {/if}
                            {/if}
                        </div>

                        <!-- Plot/Description -->
                        <div class="description card card-body bg-light mb-3">
                            {if $movie && $release.videos_id <= 0 && $movie.plot != ''}
                                <div class="description-container">
                                    <div class="descinitial">{$movie.plot|escape:"htmlall"|truncate:500:"...":true}</div>
                                    {if $movie.plot|strlen > 500}
                                        <a class="descmore badge bg-secondary text-decoration-none mt-2 mb-2" href="#" role="button">
                                            <i class="fa fa-plus-circle me-1"></i>Show more
                                        </a>
                                        <div class="descfull d-none">{$movie.plot|escape:"htmlall"|nl2br|magicurl}
                                        </div>
                                    {/if}
                                    <!-- Show less button - MOVED OUTSIDE the content div -->
                                    <a class="descless badge bg-secondary text-decoration-none mt-2 d-block d-none" href="#" role="button">
                                        <i class="fa fa-minus-circle me-1"></i>Show less
                                    </a>
                                </div>
                            {/if}

                            {if $show && $release.videos_id > 0 && $show.summary != ""}
                                <div class="description-container">
                                    <div class="descinitial">{$show.summary|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}</div>
                                    {if $show.summary|strlen > 500}
                                        <a class="descmore badge bg-secondary text-decoration-none mt-2 mb-2" href="#" role="button">
                                            <i class="fa fa-plus-circle me-1"></i>Show more
                                        </a>
                                        <div class="descfull d-none">{$show.summary|escape:"htmlall"|nl2br|magicurl}
                                        </div>
                                    {/if}
                                    <!-- Show less button - MOVED OUTSIDE the content div -->
                                    <a class="descless badge bg-secondary text-decoration-none mt-2 d-block d-none" href="#" role="button">
                                        <i class="fa fa-minus-circle me-1"></i>Show less
                                    </a>
                                </div>
                            {/if}

                            {if $xxx}
                                <div class="description-container">
                                    {if $xxx.tagline != ''}
                                        <div class="tagline mb-2"><i class="fa fa-quote-left me-1"></i>{$xxx.tagline|stripslashes|escape:"htmlall"}</div>
                                    {/if}
                                    {if $xxx.plot != ''}
                                        <div class="descinitial">
                                            <i class="fa fa-align-left me-1"></i>{$xxx.plot|stripslashes|escape:"htmlall"|truncate:500:"...":true}
                                        </div>
                                        {if $xxx.plot|strlen > 500}
                                            <a class="descmore badge bg-secondary text-decoration-none mt-2 mb-2" href="#" role="button">
                                                <i class="fa fa-plus-circle me-1"></i>Show more
                                            </a>
                                            <div class="descfull d-none">
                                                <i class="fa fa-align-left me-1"></i>{$xxx.plot|stripslashes|escape:"htmlall"}
                                            </div>
                                        {/if}
                                        <!-- Show less button - MOVED OUTSIDE the content div -->
                                        <a class="descless badge bg-secondary text-decoration-none mt-2 d-block d-none" href="#" role="button">
                                            <i class="fa fa-minus-circle me-1"></i>Show less
                                        </a>
                                    {/if}
                                </div>
                            {/if}

                            {if $anidb && $release.anidbid > 0 && $anidb.description != ""}
                                <div class="description-container">
                                    <div class="descinitial">{$anidb.description|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}</div>
                                    {if $anidb.description|strlen > 500}
                                        <a class="descmore badge bg-secondary text-decoration-none mt-2 mb-2" href="#" role="button">
                                            <i class="fa fa-plus-circle me-1"></i>Show more
                                        </a>
                                        <div class="descfull d-none">{$anidb.description|escape:"htmlall"|nl2br|magicurl}
                                        </div>
                                    {/if}
                                    <!-- Show less button - MOVED OUTSIDE the content div -->
                                    <a class="descless badge bg-secondary text-decoration-none mt-2 d-block d-none" href="#" role="button">
                                        <i class="fa fa-minus-circle me-1"></i>Show less
                                    </a>
                                </div>
                            {/if}

                            {if $music && $music.review != ""}
                                <div class="description-container">
                                    <div class="descinitial">{$music.review|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}</div>
                                    {if $music.review|strlen > 500}
                                        <a class="descmore badge bg-secondary text-decoration-none mt-2 mb-2" href="#" role="button">
                                            <i class="fa fa-plus-circle me-1"></i>Show more
                                        </a>
                                        <div class="descfull d-none">{$music.review|escape:"htmlall"|nl2br|magicurl}
                                        </div>
                                    {/if}
                                    <!-- Show less button - MOVED OUTSIDE the content div -->
                                    <a class="descless badge bg-secondary text-decoration-none mt-2 d-block d-none" href="#" role="button">
                                        <i class="fa fa-minus-circle me-1"></i>Show less
                                    </a>
                                </div>
                            {/if}

                            {if $book && $book.review != ""}
                                <div class="description-container">
                                    <div class="descinitial">{$book.review|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}</div>
                                    {if $book.review|strlen > 500}
                                        <a class="descmore badge bg-secondary text-decoration-none mt-2 mb-2" href="#" role="button">
                                            <i class="fa fa-plus-circle me-1"></i>Show more
                                        </a>
                                        <div class="descfull d-none">{$book.review|escape:"htmlall"|nl2br|magicurl}
                                        </div>
                                    {/if}
                                    <!-- Show less button - MOVED OUTSIDE the content div -->
                                    <a class="descless badge bg-secondary text-decoration-none mt-2 d-block d-none" href="#" role="button">
                                        <i class="fa fa-minus-circle me-1"></i>Show less
                                    </a>
                                </div>
                            {/if}

                            {if $con && $con.review != ""}
                                <div class="description-container">
                                    <div class="descinitial">{$con.review|escape:"htmlall"|nl2br|magicurl|truncate:500:"...":true}</div>
                                    {if $con.review|strlen > 500}
                                        <a class="descmore badge bg-secondary text-decoration-none mt-2 mb-2" href="#" role="button">
                                            <i class="fa fa-plus-circle me-1"></i>Show more
                                        </a>
                                        <div class="descfull d-none">{$con.review|escape:"htmlall"|nl2br|magicurl}
                                        </div>
                                    {/if}
                                    <!-- Show less button - MOVED OUTSIDE the content div -->
                                    <a class="descless badge bg-secondary text-decoration-none mt-2 d-block d-none" href="#" role="button">
                                        <i class="fa fa-minus-circle me-1"></i>Show less
                                    </a>
                                </div>
                            {/if}
                        </div>

                        <div class="col-md-12">
                            <div class="tabbable">
                                <div role="tabpanel">
                                    <ul class="nav nav-tabs mb-3" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link active" href="#pane1" data-bs-toggle="tab">
                                                <i class="fa fa-info-circle me-1"></i>Info
                                            </a>
                                        </li>

                                        {if ($movie && $release.videos_id <= 0 && $movie.trailer != "") || (isset($xxx.trailers) && $xxx.trailers != '')}
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" href="#pane2" data-bs-toggle="tab">
                                                    <i class="fa fa-film me-1"></i>Trailer
                                                </a>
                                            </li>
                                        {/if}

                                        {if !empty($nfo.nfo)}
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" href="#" id="nfoTab" data-bs-toggle="modal" data-bs-target="#nfoModal">
                                                    <i class="fa fa-file-alt me-1"></i>NFO
                                                </a>
                                            </li>
                                        {/if}

                                        {if !empty($similars)}
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" href="#pane4" data-bs-toggle="tab">
                                                    <i class="fa fa-clone me-1"></i>Similar
                                                </a>
                                            </li>
                                        {/if}

                                        {if $release.jpgstatus == 1 && $userdata->can('preview') == true}
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" href="#pane6" data-bs-toggle="tab">
                                                    <i class="fa fa-image me-1"></i>Sample
                                                </a>
                                            </li>
                                        {/if}

                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link" href="#comments" data-bs-toggle="tab">
                                                <i class="fa fa-comments me-1"></i>Comments
                                            </a>
                                        </li>

                                        {if ($release.haspreview == 1 || $release.haspreview == 2) && $userdata->can('preview') == true}
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" href="#pane7" data-bs-toggle="tab">
                                                    <i class="fa fa-eye me-1"></i>Preview
                                                </a>
                                            </li>
                                        {/if}

                                        {if $reVideo != false || $reAudio != false}
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" href="#pane8" data-bs-toggle="tab">
                                                    <i class="fa fa-file-video me-1"></i>MediaInfo
                                                </a>
                                            </li>
                                        {/if}

                                        {if isset($xxx.backdrop) && $xxx.backdrop == 1}
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" href="#pane9" data-bs-toggle="tab">
                                                    <i class="fa fa-image me-1"></i>Back Cover
                                                </a>
                                            </li>
                                        {/if}

                                        {if isset($game.backdrop) && $game.backdrop == 1}
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" href="#pane10" data-bs-toggle="tab">
                                                    <i class="fa fa-gamepad me-1"></i>Screenshot
                                                </a>
                                            </li>
                                        {/if}
                                    </ul>
                                </div>

                            <div class="tab-content p-3 bg-light border rounded">
                            <!-- Info Tab -->
                            <div id="pane1" class="tab-pane active">
                                <h5 class="mb-3">Information</h5>
                                <div class="row small-gutter-left">
                                    <!-- Cover/Poster Column -->
                                    <div class="col-md-3 small-gutter-left">
                                        {if $movie && $release.videos_id <= 0 && $movie.cover == 1}
                                            <img src="{{url("/covers/movies/{$movie.imdbid}-cover.jpg")}}" width="185"
                                                 alt="{$movie.title|escape:"htmlall"}" data-bs-toggle="modal" data-bs-target="#modal-image"/>
                                        {elseif $show && $release.videos_id > 0 && $show.image != "0"}
                                            <img src="{{url("/covers/tvshows/{$release.videos_id}.jpg")}}" width="185"
                                                 alt="{$show.title|escape:"htmlall"}" data-bs-toggle="modal" data-bs-target="#modal-image"/>
                                        {elseif $anidb && $release.anidbid > 0 && $anidb.picture != ""}
                                            <img src="{{url("/covers/anime/{$anidb.anidbid}.jpg")}}" width="185"
                                                 alt="{$anidb.title|escape:"htmlall"}" data-bs-toggle="modal" data-bs-target="#modal-image"/>
                                        {elseif $con && $con.cover == 1}
                                            <img src="{{url("/covers/console/{$con.id}.jpg")}}" width="185"
                                                 alt="{$con.title|escape:"htmlall"}" data-bs-toggle="modal" data-bs-target="#modal-image"/>
                                        {elseif $game && $game.cover == 1}
                                            <img src="{{url("/covers/games/{$game.id}.jpg")}}" width="185"
                                                 alt="{$con.title|escape:"htmlall"}" data-bs-toggle="modal" data-bs-target="#modal-image"/>
                                        {elseif $music && $music.cover == 1}
                                            <img src="{{url("/covers/music/{$music.id}.jpg")}}" width="185"
                                                 alt="{$music.title|escape:"htmlall"}" data-bs-toggle="modal" data-bs-target="#modal-image"/>
                                        {elseif $book && $book.cover == 1}
                                            <img src="{{url("/covers/book/{$book.id}.jpg")}}" width="185"
                                                 alt="{$book.title|escape:"htmlall"}" data-bs-toggle="modal" data-bs-target="#modal-image"/>
                                        {elseif $xxx && $xxx.cover == 1}
                                            <a href="{{url("/covers/xxx/{$xxx.id}-cover.jpg")}}" class="modal-image">
                                                <img class="modal-image" src="{{url("/covers/xxx/{$xxx.id}-cover.jpg")}}" width="185"
                                                     alt="{$xxx.title|escape:"htmlall"}" data-bs-toggle="modal" data-bs-target="#modal-image"/>
                                            </a>
                                        {/if}
                                        <br/><br/>

                                        <!-- Download Buttons -->
                                        <div class="download-actions mb-3">
                                            <div class="d-flex flex-wrap gap-2">
                                                <a class="btn btn-success" href="{{url("/getnzb?id={$release.guid}")}}">
                                                    <i class="fa fa-cloud-download-alt me-1"></i>Download NZB
                                                </a>
                                                <a href="#" class="btn btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Add to Cart">
                                                    <i id="guid{$release.guid}" class="icon_cart fa fa-shopping-cart"></i> Add to Cart
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Details Column -->
                                    <div class="col-md-9 small-gutter-left">
                                        <table class="data table table-striped responsive-utilities jambo-table">
                                            <!-- Title/Name Information -->
                                            {if $movie && $release.videos_id <= 0 && $movie.imdbid > 0}
                                                <tr>
                                                    <th width="140"><i class="fa fa-film me-2 text-secondary"></i>Name</th>
                                                    <td class="fw-medium">{$movie.title|escape:"htmlall"}</td>
                                                </tr>
                                            {elseif $show && $release.videos_id > 0}
                                                <tr>
                                                    <th width="140"><i class="fa fa-tv me-2 text-secondary"></i>Name</th>
                                                    <td class="fw-medium">{$release.title|escape:"htmlall"}</td>
                                                </tr>
                                            {elseif $xxx}
                                                <tr>
                                                    <th width="140"><i class="fa fa-film me-2 text-secondary"></i>Name</th>
                                                    <td class="fw-medium">{$xxx.title|stripslashes|escape:"htmlall"}</td>
                                                </tr>
                                                <tr>
                                                    <th width="140"><i class="fa fa-users me-2 text-secondary"></i>Starring</th>
                                                    <td>{$xxx.actors}</td>
                                                </tr>
                                                {if isset($xxx.director) && $xxx.director != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-user me-2 text-secondary"></i>Director</th>
                                                        <td>{$xxx.director}</td>
                                                    </tr>
                                                {/if}
                                                {if isset($xxx.genres) && $xxx.genres != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-tag me-2 text-secondary"></i>Genre</th>
                                                        <td>{$xxx.genres}</td>
                                                    </tr>
                                                {/if}
                                            {/if}

                                            <!-- Movie Details -->
                                            {if $movie && $release.videos_id <= 0 && $movie.imdbid > 0 }
                                                <tr>
                                                    <th width="140"><i class="fa fa-users me-2 text-secondary"></i>Starring</th>
                                                    <td>{$movie.actors}</td>
                                                </tr>
                                                <tr>
                                                    <th width="140"><i class="fa fa-user me-2 text-secondary"></i>Director</th>
                                                    <td>{$movie.director}</td>
                                                </tr>
                                                <tr>
                                                    <th width="140"><i class="fa fa-tag me-2 text-secondary"></i>Genre</th>
                                                    <td>{$movie.genre}</td>
                                                </tr>
                                                <tr>
                                                    <th width="140"><i class="fa fa-calendar me-2 text-secondary"></i>Year & Rating</th>
                                                    <td><span class="badge bg-secondary">{$movie.year}</span> - <span class="fw-medium">{if $movie.rating == ''}N/A{else}{$movie.rating}/10{/if}</span></td>
                                                </tr>
                                                {if !empty($movie.rtrating)}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-star me-2 text-secondary"></i>RottenTomatoes</th>
                                                        <td><span class="badge bg-dark">{$movie.rtrating}</span></td>
                                                    </tr>
                                                {/if}
                                            {/if}

                                            <!-- TV Show Details -->
                                            {if $show && $release.videos_id > 0}
                                                {if $release.firstaired != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-calendar me-2 text-secondary"></i>Aired</th>
                                                        <td>{$release.firstaired|date_format}</td>
                                                    </tr>
                                                {/if}
                                                {if $show.publisher != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-building me-2 text-secondary"></i>Network</th>
                                                        <td>{$show.publisher}</td>
                                                    </tr>
                                                {/if}
                                                {if $show.countries_id != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-globe me-2 text-secondary"></i>Country</th>
                                                        <td>{$show.countries_id}</td>
                                                    </tr>
                                                {/if}
                                            {/if}

                                            <!-- Music Details -->
                                            {if $music}
                                                <tr>
                                                    <th width="140"><i class="fa fa-music me-2 text-secondary"></i>Name</th>
                                                    <td class="fw-medium">{$music.title|escape:"htmlall"}</td>
                                                </tr>
                                                <tr>
                                                    <th width="140"><i class="fa fa-tag me-2 text-secondary"></i>Genre</th>
                                                    <td>{$music.genres|escape:"htmlall"}</td>
                                                </tr>
                                                {if $music.releasedate != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-calendar me-2 text-secondary"></i>Release Date</th>
                                                        <td>{$music.releasedate|date_format}</td>
                                                    </tr>
                                                {/if}
                                                {if $music.publisher != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-building me-2 text-secondary"></i>Publisher</th>
                                                        <td>{$music.publisher|escape:"htmlall"}</td>
                                                    </tr>
                                                {/if}
                                            {/if}

                                            <!-- Book Details -->
                                            {if $book}
                                                <tr>
                                                    <th width="140"><i class="fa fa-book me-2 text-secondary"></i>Name</th>
                                                    <td class="fw-medium">{$book.title|escape:"htmlall"}</td>
                                                </tr>
                                                <tr>
                                                    <th width="140"><i class="fa fa-user me-2 text-secondary"></i>Author</th>
                                                    <td>{$book.author|escape:"htmlall"}</td>
                                                </tr>
                                                {if $book.ean != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-barcode me-2 text-secondary"></i>EAN</th>
                                                        <td><code>{$book.ean|escape:"htmlall"}</code></td>
                                                    </tr>
                                                {/if}
                                                {if $book.isbn != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-barcode me-2 text-secondary"></i>ISBN</th>
                                                        <td><code>{$book.isbn|escape:"htmlall"}</code></td>
                                                    </tr>
                                                {/if}
                                                {if $book.pages != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-file me-2 text-secondary"></i>Pages</th>
                                                        <td>{$book.pages|escape:"htmlall"}</td>
                                                    </tr>
                                                {/if}
                                                {if $book.dewey != ""}
                                                    <tr>
                                                        <th width="140"><i class="fa fa-list-ol me-2 text-secondary"></i>Dewey</th>
                                                        <td>{$book.dewey|escape:"htmlall"}</td>
                                                    </tr>
                                                {/if}
                                            {/if}

                                            <!-- Common Release Information -->
                                            <tr>
                                                <th width="140"><i class="fa fa-users me-2 text-secondary"></i>Group(s)</th>
                                                <td>
                                                    {if !empty($release.group_names)}
                                                        {assign var="groupname" value=","|explode:$release.group_names}
                                                        {foreach $groupname as $grp}
                                                            <a class="d-block text-decoration-none mb-1" title="Browse {$grp}" href="{{url("/browse/group?g={$grp}")}}">
                                                                <i class="fa fa-layer-group me-1 text-muted"></i>{$grp|replace:"alt.binaries":"a.b"}
                                                            </a>
                                                        {/foreach}
                                                    {else}
                                                        <a class="text-decoration-none" title="Browse {$release.group_name}" href="{{url("/browse/group?g={$release.group_name}")}}">
                                                            <i class="fa fa-layer-group me-1 text-muted"></i>{$release.group_name|replace:"alt.binaries":"a.b"}
                                                        </a>
                                                    {/if}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th width="140"><i class="fa fa-hdd-o me-2 text-secondary"></i>Size / Completion</th>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="fw-medium">{$release.size|filesize}</span>
                                                        {if $release.completion > 0}
                                                            <span class="ms-2">
                                                                ({if $release.completion < 100}<span class="text-muted">{$release.completion}%</span>{else}{$release.completion}%{/if})
                                                            </span>
                                                        {/if}
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th width="140"><i class="fa fa-download me-2 text-secondary"></i>Grabs</th>
                                                <td><span class="fw-medium">{$release.grabs}</span> time{if $release.grabs != 1}s{/if}</td>
                                            </tr>
                                            {if !empty($failed)}
                                                <tr>
                                                    <th width="140"><i class="fa fa-exclamation-triangle me-2 text-dark"></i>Failed Download</th>
                                                    <td><span class="fw-medium text-dark">{$failed}</span> time{if $failed != 1}s{/if}</td>
                                                </tr>
                                            {/if}
                                            <tr>
                                                <th width="140"><i class="fa fa-key me-2 text-secondary"></i>Password</th>
                                                <td>
                                                    {if $release.passwordstatus == 0}
                                                        <span class="badge bg-secondary">None</span>
                                                    {elseif $release.passwordstatus == 1}
                                                        <span class="badge bg-secondary">Unknown</span>
                                                    {else}
                                                        <span class="badge bg-dark">Passworded Rar Archive</span>
                                                    {/if}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th width="140"><i class="fa fa-folder me-2 text-secondary"></i>Category</th>
                                                <td>
                                                    <a class="text-decoration-none" title="Browse {$release.category_name}" href="{{url("/browse/{$release.parent_category}/{$release.sub_category}")}}">
                                                        <i class="fa fa-tag me-1 text-muted"></i>{$release.category_name}
                                                    </a>
                                                </td>
                                            </tr>
                                           <tr>
                                                <th width="140"><i class="fa fa-file me-2 text-secondary"></i>Files</th>
                                                <td>
                                                    <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#filelistModal" data-guid="{$release.guid}">
                                                        <i class="far fa-file me-1 text-muted"></i><span class="fw-medium">{$release.totalpart}</span> file{if $release.totalpart != 1}s{/if}
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th width="140"><i class="fa fa-archive me-2 text-secondary"></i>RAR Contains</th>
                                                <td>
                                                    <strong>Files:</strong>
                                                    <div class="mt-2">
                                                        {foreach $releasefiles as $rf}
                                                            <div class="mb-3">
                                                                <code class="d-block mb-1">{$rf.name}</code>
                                                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                                                    {if $rf.passworded != 1}
                                                                        <span class="badge bg-secondary"><i class="fa fa-unlock me-1"></i>No Password</span>
                                                                    {else}
                                                                        <span class="badge bg-dark"><i class="fa fa-lock me-1"></i>Passworded</span>
                                                                    {/if}
                                                                    <span class="badge bg-light text-dark"><i class="fa fa-hdd-o me-1"></i>{$rf.size|filesize}</span>
                                                                    <span class="badge bg-secondary"><i class="fa fa-calendar me-1"></i>{$rf.created_at|date_format}</span>
                                                                </div>
                                                            </div>
                                                        {/foreach}
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th width="140"><i class="fa fa-user me-2 text-secondary"></i>Poster</th>
                                                <td>
                                                    <a class="text-decoration-none" title="Find releases by this poster" href="{{url("/search?searchadvr=&searchadvsubject=&searchadvposter={$release.fromname|escape:"htmlall"}&searchadvfilename=&searchadvdaysnew=&searchadvdaysold=&searchadvgroups=-1&searchadvcat=-1&searchadvsizefrom=-1&searchadvsizeto=-1&searchadvhasnfo=0&searchadvhascomments=0&search_type=adv")}}">
                                                        <i class="fa fa-user-circle me-1 text-muted"></i>{$release.fromname|escape:"htmlall"}
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th width="140"><i class="fa fa-clock-o me-2 text-secondary"></i>Posted</th>
                                                <td>{$release.postdate}</td>
                                            </tr>
                                            <tr>
                                                <th width="140"><i class="fa fa-plus-circle me-2 text-secondary"></i>Added</th>
                                                <td>{$release.adddate}</td>
                                            </tr>
                                            {if isset($isadmin)}
                                                <tr>
                                                    <th width="140"><i class="fa fa-info-circle me-2 text-secondary"></i>Release Info</th>
                                                    <td>
                                                        {if !empty($regex->collection_regex_id)}
                                                            <div class="mb-1"><span class="fw-medium">Collection regex ID:</span> {$regex->collection_regex_id}</div>
                                                        {/if}
                                                        {if !empty($regex->naming_regex_id)}
                                                            <div class="mb-1"><span class="fw-medium">Naming regex ID:</span> {$regex->naming_regex_id}</div>
                                                        {/if}
                                                        {if !empty($downloadedby) && count($downloadedby)>0}
                                                            <div class="mt-2">
                                                                <span class="fw-medium">Release downloaded by:</span>
                                                                <div class="mt-1">
                                                                    {foreach $downloadedby as $user}
                                                                        <a class="badge bg-light text-dark text-decoration-none me-1 mb-1" href="{{url("/admin/user-edit?id={$user->user->id}")}}">
                                                                            <i class="fa fa-user me-1"></i>{$user->user->username}
                                                                        </a>
                                                                    {/foreach}
                                                                </div>
                                                            </div>
                                                        {/if}
                                                    </td>
                                                </tr>
                                            {/if}
												</td>
											</tr>
											</tbody>
										</table>
									</div>
								</div>
							</div>
    <div id="pane2" class="tab-pane">
        <h5 class="mb-3">Trailer</h5>
        {if $xxx && $xxx.trailers != ''}
            {$xxx.trailers}
        {elseif $movie && $release.videos_id <= 0 && $movie.trailer != ''}
            {$movie.trailer}
        {/if}
    </div>
<!-- NFO content will be shown in modal instead of tab -->
<div id="pane4" class="tab-pane">
    {if !empty($similars)}
        <div class="card card-default">
            <div class="card-header">
                <h5 class="card-title">Similar Releases</h5>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    {foreach $similars as $similar}
                        <li class="list-group-item">
                            <a title="View similar NZB details" href="{{url("/details/{$similar.guid}")}}">
                                {$similar.searchname|escape:"htmlall"}
                            </a>
                        </li>
                    {/foreach}
                </ul>
                <div class="mt-3">
                    <a class="btn btn-sm btn-outline-secondary" title="Search for similar Nzbs"
                       href="{{url("/search?id={$searchname|escape:"htmlall"}")}}">
                        Search for similar NZBs...
                    </a>
                </div>
            </div>
        </div>
    {/if}
</div>

<div id="comments" class="tab-pane">
    {if $comments|@count > 0}
        <table class="data table table-striped responsive-utilities jambo-table">
            <thead>
                <tr>
                    <th width="160">User</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                {foreach $comments|@array_reverse:true as $comment}
                    <tr>
                        <td class="less" title="{$comment.created_at}">
                            {if !$privateprofiles || isset($isadmin) || isset($ismod)}
                                <a title="View {$comment.username}'s profile" href="{{url("/profile?name={$comment.username}")}}">
                                    {$comment.username}
                                </a>
                            {else}
                                {$comment.username}
                            {/if}
                            <br/>{$comment.created_at|daysago}
                        </td>
                        <td class="{if isset($comment.shared) && $comment.shared == 2}text-danger{/if}">
                            {$comment.text|escape:"htmlall"|nl2br}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {else}
        <div class="alert alert-info" role="alert">
            No comments yet...
        </div>
    {/if}

    <div class="card mt-3">
        <div class="card-header">
            <h5 class="card-title">Add Comment</h5>
        </div>
        <div class="card-body">
            {{Form::open(['url' => "details/{$release.guid}"])}}
                <div class="mb-3">
                    <textarea id="txtAddComment" name="txtAddComment" class="form-control" rows="4"></textarea>
                </div>
                {{Form::submit('Submit', ['class' => 'btn btn-success'])}}
            {{Form::close()}}
        </div>
    </div>
</div>

{if $release.jpgstatus == 1 && $userdata->can('preview') == true}
    <div id="pane6" class="tab-pane">
        <img src="{{url("/covers/sample/{$release.guid}_thumb.jpg")}}"
             alt="{$release.searchname|escape:"htmlall"}"
             class="img-fluid"
             data-bs-toggle="modal"
             data-bs-target="#modal-image"/>
    </div>
{/if}

{if ($release.haspreview == 1 || $release.haspreview == 2) && $userdata->can('preview') == true}
    <div id="pane7" class="tab-pane">
        <img src="{{url("/covers/preview/{$release.guid}_thumb.jpg")}}"
             alt="{$release.searchname|escape:"htmlall"}"
             class="img-fluid"
             data-bs-toggle="modal"
             data-bs-target="#modal-image"/>
    </div>
{/if}

{if $reVideo != false || $reAudio != false}
    <div id="pane8" class="tab-pane">
        <div class="card card-default">
            <div class="card-header">
                <h5 class="card-title">Media Information</h5>
            </div>
            <div class="card-body">
                <table class="data table table-striped responsive-utilities jambo-table">
                    <thead>
                        <tr>
                            <th width="15%"></th>
                            <th>Property</th>
                            <th class="text-end">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        {if $reVideo.containerformat != ""}
                            <tr>
                                <td><strong>Overall</strong></td>
                                <td>Container Format</td>
                                <td class="text-end">{$reVideo.containerformat}</td>
                            </tr>
                        {/if}
                        {if $reVideo.overallbitrate != ""}
                            <tr>
                                <td></td>
                                <td>Bitrate</td>
                                <td class="text-end">{$reVideo.overallbitrate}</td>
                            </tr>
                        {/if}
                        {if $reVideo.videoduration != ""}
                            <tr>
                                <td><strong>Video</strong></td>
                                <td>Duration</td>
                                <td class="text-end">{$reVideo.videoduration}</td>
                            </tr>
                        {/if}
                        {if $reVideo.videoformat != ""}
                            <tr>
                                <td></td>
                                <td>Format</td>
                                <td class="text-end">{$reVideo.videoformat}</td>
                            </tr>
                        {/if}
                        {if $reVideo.videocodec != ""}
                            <tr>
                                <td></td>
                                <td>Codec</td>
                                <td class="text-end">{$reVideo.videocodec}</td>
                            </tr>
                        {/if}
                        {if $reVideo.videowidth != "" && $reVideo.videoheight != ""}
                            <tr>
                                <td></td>
                                <td>Resolution</td>
                                <td class="text-end">{$reVideo.videowidth} x {$reVideo.videoheight}</td>
                            </tr>
                        {/if}
                        {if $reVideo.videoaspect != ""}
                            <tr>
                                <td></td>
                                <td>Aspect</td>
                                <td class="text-end">{$reVideo.videoaspect}</td>
                            </tr>
                        {/if}
                        {if $reVideo.videoframerate != ""}
                            <tr>
                                <td></td>
                                <td>Framerate</td>
                                <td class="text-end">{$reVideo.videoframerate} fps</td>
                            </tr>
                        {/if}
                        {if $reVideo.videolibrary != ""}
                            <tr>
                                <td></td>
                                <td>Library</td>
                                <td class="text-end">{$reVideo.videolibrary}</td>
                            </tr>
                        {/if}
                        {foreach $reAudio as $audio}
                            <tr>
                                <td><strong>Audio {$audio.audioid}</strong></td>
                                <td>Format</td>
                                <td class="text-end">{$audio.audioformat}</td>
                            </tr>
                            {if $audio.audiolanguage != ""}
                                <tr>
                                    <td></td>
                                    <td>Language</td>
                                    <td class="text-end">{$audio.audiolanguage}</td>
                                </tr>
                            {/if}
                            {if $audio.audiotitle != ""}
                                <tr>
                                    <td></td>
                                    <td>Title</td>
                                    <td class="text-end">{$audio.audiotitle}</td>
                                </tr>
                            {/if}
                            {if $audio.audiomode != ""}
                                <tr>
                                    <td></td>
                                    <td>Mode</td>
                                    <td class="text-end">{$audio.audiomode}</td>
                                </tr>
                            {/if}
                            {if $audio.audiobitratemode != ""}
                                <tr>
                                    <td></td>
                                    <td>Bitrate Mode</td>
                                    <td class="text-end">{$audio.audiobitratemode}</td>
                                </tr>
                            {/if}
                            {if $audio.audiobitrate != ""}
                                <tr>
                                    <td></td>
                                    <td>Bitrate</td>
                                    <td class="text-end">{$audio.audiobitrate}</td>
                                </tr>
                            {/if}
                            {if $audio.audiochannels != ""}
                                <tr>
                                    <td></td>
                                    <td>Channels</td>
                                    <td class="text-end">{$audio.audiochannels}</td>
                                </tr>
                            {/if}
                            {if $audio.audiosamplerate != ""}
                                <tr>
                                    <td></td>
                                    <td>Sample Rate</td>
                                    <td class="text-end">{$audio.audiosamplerate}</td>
                                </tr>
                            {/if}
                            {if $audio.audiolibrary != ""}
                                <tr>
                                    <td></td>
                                    <td>Library</td>
                                    <td class="text-end">{$audio.audiolibrary}</td>
                                </tr>
                            {/if}
                        {/foreach}
                        {if $reSubs.subs != ""}
                            <tr>
                                <td><strong>Subtitles</strong></td>
                                <td>Languages</td>
                                <td class="text-end">{$reSubs.subs|escape:"htmlall"}</td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
{/if}

{if isset($xxx.backdrop) && $xxx.backdrop == 1}
    <div id="pane9" class="tab-pane">
        <img src="{{url("/covers/xxx/{$xxx.id}-backdrop.jpg")}}"
             alt="{$xxx.title|escape:"htmlall"}"
             class="img-fluid"
             data-bs-toggle="modal"
             data-bs-target="#modal-image"/>
    </div>
{/if}

{if isset($game.backdrop) && $game.backdrop == 1}
    <div id="pane10" class="tab-pane">
        <img src="{{url("/covers/games/{$game.id}-backdrop.jpg")}}"
             width="500" class="img-fluid"
             alt="{$game.title|escape:"htmlall"}"
             data-bs-toggle="modal"
             data-bs-target="#modal-image"/>
    </div>
{/if}

<!-- End of Tabs Content -->
</div>
</div>
<!-- /.tab-content -->
</div>
<!-- /.tabbable -->
</div>
</div>
</div>
</div>

<!-- NFO Modal -->
<div class="modal fade" id="nfoModal" tabindex="-1" aria-labelledby="nfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nfoModalLabel">NFO Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3 nfo-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading NFO content...</p>
                </div>
                <pre id="nfoContent" class="bg-dark text-light p-3 rounded d-none" style="white-space: pre; font-family: monospace; overflow-x: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="modal-image" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Image Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                {if $movie && $release.videos_id <= 0 && $movie.cover == 1}
                    <img src="{{url("/covers/movies/{$movie.imdbid}-cover.jpg")}}" class="img-fluid" alt="{$movie.title|escape:"htmlall"}">
                {elseif $show && $release.videos_id > 0 && $show.image != "0"}
                    <img src="{{url("/covers/tvshows/{$release.videos_id}.jpg")}}" class="img-fluid" alt="{$show.title|escape:"htmlall"}"/>
                {elseif $anidb && $release.anidbid > 0 && $anidb.picture != ""}
                    <img src="{{url("/covers/anime/{$anidb.anidbid}.jpg")}}" class="img-fluid" alt="{$anidb.title|escape:"htmlall"}"/>
                {elseif $con && $con.cover == 1}
                    <img src="{{url("/covers/console/{$con.id}.jpg")}}" class="img-fluid" alt="{$con.title|escape:"htmlall"}"/>
                {elseif $music && $music.cover == 1}
                    <img src="{{url("/covers/music/{$music.id}.jpg")}}" class="img-fluid" alt="{$music.title|escape:"htmlall"}"/>
                {elseif $book && $book.cover == 1}
                    <img src="{{url("/covers/book/{$book.id}.jpg")}}" class="img-fluid" alt="{$book.title|escape:"htmlall"}"/>
                {elseif $xxx && $xxx.backdrop == 1}
                    <img src="{{url("/covers/xxx/{$xxx.id}-backdrop.jpg")}}" class="img-fluid" alt="{$xxx.title|escape:"htmlall"}"/>
                {elseif $xxx && $xxx.cover == 1}
                    <img src="{{url("/covers/xxx/{$xxx.id}-cover.jpg")}}" class="img-fluid" alt="{$xxx.title|escape:"htmlall"}"/>
                {/if}
            </div>
        </div>
    </div>
</div>

<!-- File List Modal -->
<div class="modal fade" id="filelistModal" tabindex="-1" aria-labelledby="filelistModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filelistModalLabel">File List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3 filelist-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading file list...</p>
                </div>
                <div id="filelistContent" class="d-none">
                    <div class="mb-3 d-flex justify-content-between">
                        <span class="text-muted small">Total Files: <span id="total-files">0</span></span>
                        <span class="text-muted small">Total Size: <span id="total-size">0 B</span></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 40px" class="text-center">#</th>
                                    <th>Filename</th>
                                    <th style="width: 60px" class="text-center">Type</th>
                                    <th style="width: 120px" class="text-center">Completion</th>
                                    <th style="width: 100px" class="text-center">Size</th>
                                </tr>
                            </thead>
                            <tbody id="filelist-tbody">
                                <!-- Files will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
{literal}
    // NFO Modal content loading
    document.addEventListener('DOMContentLoaded', function() {
        const nfoModal = document.getElementById('nfoModal');
        const nfoTabLink = document.getElementById('nfoTab');

        if (nfoModal && nfoTabLink) {
            nfoModal.addEventListener('show.bs.modal', function() {
                const loading = nfoModal.querySelector('.nfo-loading');
                const contentElement = document.getElementById('nfoContent');
                const existingNfo = document.getElementById('nfo');

                // Reset and show loading state
                loading.style.display = 'block';
                contentElement.classList.add('d-none');

                // Use existing NFO content if available
                if (existingNfo && existingNfo.textContent) {
                    loading.style.display = 'none';
                    contentElement.classList.remove('d-none');
                    contentElement.textContent = existingNfo.textContent;
                } else {
                    // Fetch the NFO content via AJAX if not already loaded
                    fetch(`/nfo/{$release.guid}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.text())
                    .then(html => {
                        // Extract just the NFO content from the response
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        // Look for the pre element that likely contains the NFO
                        let nfoText = '';
                        const preElement = doc.querySelector('pre');

                        if (preElement) {
                            nfoText = preElement.textContent;
                        } else {
                            const mainContent = doc.querySelector('.card-body, .main-content, .content-area, main');
                            if (mainContent) {
                                nfoText = mainContent.textContent;
                            } else {
                                nfoText = doc.body.textContent;
                            }
                        }

                        // Update the modal
                        loading.style.display = 'none';
                        contentElement.classList.remove('d-none');
                        contentElement.textContent = nfoText.trim();
                    })
                    .catch(error => {
                        console.error('Error fetching NFO content:', error);
                        loading.style.display = 'none';
                        contentElement.classList.remove('d-none');
                        contentElement.textContent = 'Error loading NFO content';
                    });
                }
            });
        }
    });
    // Description show/hide functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Show more button
        document.querySelectorAll('.descmore').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const container = this.closest('.description-container');
                const initial = container.querySelector('.descinitial');
                const full = container.querySelector('.descfull');

                initial.classList.add('d-none');
                full.classList.remove('d-none');
                this.classList.add('d-none'); // Hide "Show more" button
            });
        });

        // Show less button
        document.querySelectorAll('.descless').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const container = this.closest('.description-container');
                const initial = container.querySelector('.descinitial');
                const full = container.querySelector('.descfull');
                const showMoreBtn = container.querySelector('.descmore');

                initial.classList.remove('d-none');
                full.classList.add('d-none');
                showMoreBtn.classList.remove('d-none'); // Show "Show more" button again
            });
        });
    });
    // File List Modal
    document.addEventListener('DOMContentLoaded', function() {
        const filelistModal = document.getElementById('filelistModal');

        if (filelistModal) {
            filelistModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const guid = button.getAttribute('data-guid');
                const loading = filelistModal.querySelector('.filelist-loading');
                const contentElement = document.getElementById('filelistContent');
                const tbody = document.getElementById('filelist-tbody');
                const totalFiles = document.getElementById('total-files');
                const totalSize = document.getElementById('total-size');

                // Reset and show loading state
                loading.style.display = 'block';
                contentElement.classList.add('d-none');
                tbody.innerHTML = '';

                // Fetch the file list via AJAX
                fetch(`/filelist/${guid}?modal=true`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // Extract data from the HTML response
                    const files = [];
                    const tableRows = doc.querySelectorAll('table tbody tr');
                    let totalSizeBytes = 0;

                    tableRows.forEach(row => {
                        const cells = row.querySelectorAll('td');
                        if (cells.length >= 5) {
                            const file = {
                                num: cells[0].textContent.trim(),
                                filename: cells[1].querySelector('.text-truncate').getAttribute('title'),
                                ext: cells[2].querySelector('.badge') ? cells[2].querySelector('.badge').textContent.trim() : '',
                                completion: cells[3].querySelector('.progress-bar') ?
                                    cells[3].querySelector('.progress-bar').getAttribute('aria-valuenow') : '100',
                                size: cells[4].textContent.trim()
                            };

                            // Parse filesize for total calculation
                            const sizeMatch = file.size.match(/(\d+(\.\d+)?)\s*(KB|MB|GB|TB)/i);
                            if (sizeMatch) {
                                const size = parseFloat(sizeMatch[1]);
                                const unit = sizeMatch[3].toUpperCase();
                                let bytes = size;
                                if (unit === 'KB') bytes *= 1024;
                                else if (unit === 'MB') bytes *= 1024 * 1024;
                                else if (unit === 'GB') bytes *= 1024 * 1024 * 1024;
                                else if (unit === 'TB') bytes *= 1024 * 1024 * 1024 * 1024;
                                totalSizeBytes += bytes;
                            }

                            files.push(file);
                        }
                    });

                    // Format total size
                    function formatFileSize(bytes) {
                        if (bytes < 1024) return bytes + ' B';
                        else if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
                        else if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
                        else if (bytes < 1024 * 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
                        else return (bytes / (1024 * 1024 * 1024 * 1024)).toFixed(2) + ' TB';
                    }

                    // Display total information
                    totalFiles.textContent = files.length;
                    totalSize.textContent = formatFileSize(totalSizeBytes);

                    // Populate the modal with files
                    files.forEach(file => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="text-center">${file.num}</td>
                            <td class="text-break">
                                <span class="d-inline-block text-truncate" style="max-width: 400px;" title="${file.filename}">${file.filename}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary text-uppercase">${file.ext}</span>
                            </td>
                            <td class="text-center">
                                <div class="progress" style="height: 20px">
                                    <div class="progress-bar ${file.completion < 100 ? 'bg-warning' : 'bg-success'}"
                                         role="progressbar"
                                         style="width: ${file.completion}%"
                                         aria-valuenow="${file.completion}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                        ${file.completion}%
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fa fa-hdd-o text-muted me-2"></i>
                                    <span class="fw-medium">${file.size}</span>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });

                    // Show content, hide loading
                    loading.style.display = 'none';
                    contentElement.classList.remove('d-none');
                })
                .catch(error => {
                    console.error('Error fetching file list:', error);
                    loading.style.display = 'none';
                    contentElement.classList.remove('d-none');
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading file list</td></tr>';
                });
            });
        }
    });

    // Add to Cart functionality
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(e) {
            // First find if the click target or any parent is the cart icon
            const cartIcon = e.target.closest('.icon_cart');

            // Or check if the click target is a parent element with a child cart icon
            const parentWithCart = e.target.closest('a') && e.target.closest('a').querySelector('.icon_cart');

            // Exit early if neither applies - important to prevent triggering on any click
            if (!cartIcon && !parentWithCart) return;

            // Get the actual icon element
            const iconElement = cartIcon || (parentWithCart ? e.target.closest('a').querySelector('.icon_cart') : null);

            // Secondary validation to prevent errors
            if (!iconElement || !iconElement.id) return;
            if (iconElement.classList.contains('icon_cart_clicked')) return;

            // Now we can safely call preventDefault() since we know it's a cart click
            e.preventDefault();

            const guid = iconElement.id.replace('guid', '');
            const originalIcon = iconElement.classList.contains('fa-shopping-cart') ? 'fa-shopping-cart' : 'fa-shopping-basket';

            // Send AJAX request
            fetch(`${base_url}/cart/add?id=${guid}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => {
                if (response.ok) {
                    // Success feedback
                    iconElement.classList.remove(originalIcon);
                    iconElement.classList.add('fa-check', 'icon_cart_clicked');
                    iconElement.setAttribute('title', 'Release added to Cart');

                    // Show notification with 5000ms timeout
                    if (typeof PNotify !== 'undefined') {
                        PNotify.success({
                            title: 'Release added to your download basket!',
                            icon: 'fa fa-info fa-3x',
                            delay: 5000
                        });
                    }

                    // Restore original icon after delay
                    setTimeout(() => {
                        iconElement.classList.remove('fa-check');
                        iconElement.classList.add(originalIcon);
                    }, 1500);
                } else {
                    throw new Error('Network response was not ok');
                }
            })
            .catch(error => {
                console.error('Cart error:', error);

                iconElement.classList.remove(originalIcon);
                iconElement.classList.add('fa-times');

                if (typeof PNotify !== 'undefined') {
                    PNotify.error({
                        title: 'Error',
                        text: 'Failed to add item to cart',
                        icon: 'fa fa-exclamation-circle',
                        delay: 5000
                    });
                }

                setTimeout(() => {
                    iconElement.classList.remove('fa-times');
                    iconElement.classList.add(originalIcon);
                }, 1500);
            });
        });
    });
{/literal}
</script>

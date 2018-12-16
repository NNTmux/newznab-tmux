<div class="navbar navbar-expand-md navbar-dark bg-dark mb-4" role="navigation">
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarCollapse">
        {if isset($userdata)}
            {foreach $parentcatlist as $parentcat}
                {if $parentcat.id == {$catClass::TV_ROOT}}
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" data-close-others="true" data-delay="30">
                                <i class="fa fa-television"></i> {$parentcat.title}
                            </a>
                            <ul class="dropdown-menu">
                                <li class="dropdown-item">
                                    <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">TV</a>
                                </li>
                                <hr>
                                <li class="dropdown-item">
                                    <a href="{$smarty.const.WWW_TOP}/series">TV Series</a>
                                </li>
                                <li class="dropdown-item">
                                    <a href="{$smarty.const.WWW_TOP}/animelist">Anime Series</a>
                                </li>
                                <hr>
                                {foreach $parentcat.subcatlist as $subcat}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/browse/TV/{$subcat.title}">{$subcat.title}</a>
                                    </li>
                                {/foreach}
                            </ul>
                        </li>
                    </ul>
                {/if}
                {if $parentcat.id == {$catClass::MOVIE_ROOT}}
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" data-close-others="true" data-delay="30">
                                <i class="fa fa-film"></i> {$parentcat.title}
                            </a>
                            <ul class="dropdown-menu">
                                {if $userdata.movieview == "1"}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a>
                                    </li>
                                {elseif $userdata.movieview != "1"}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
                                    </li>
                                {/if}
                                <hr>
                                <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/mymovies">My Movies</a></li>
                                <hr>
                                {if $userdata.movieview == "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item">
                                            <a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {elseif $userdata.movieview != "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item">
                                            <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {/if}
                            </ul>
                        </li>
                    </ul>
                {/if}
                {if $parentcat.id == {$catClass::GAME_ROOT}}
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" data-close-others="true"
                               data-delay="30">
                                <i class="fa fa-gamepad"></i> {$parentcat.title}
                            </a>
                            <ul class="dropdown-menu">
                                {if $userdata.consoleview == "1"}
                                    <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
                                {elseif $userdata.consoleview != "1"}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
                                    </li>
                                {/if}
                                <hr>
                                {if $userdata.consoleview == "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {elseif $userdata.consoleview != "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item">
                                            <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {/if}
                            </ul>
                        </li>
                    </ul>
                {/if}
                {if $parentcat.id == {$catClass::PC_ROOT}}
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" data-close-others="true"
                               data-delay="30">
                                <i class="fa fa-gamepad"></i> {$parentcat.title}
                            </a>
                            <ul class="dropdown-menu">
                                {if $userdata.gameview == "1"}
                                    <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a></li>
                                {elseif $userdata.gameview != "1"}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
                                    </li>
                                {/if}
                                <hr>
                                {if $userdata.gameview == "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        {if $subcat.id == {$catClass::PC_GAMES}}
                                            <li class="dropdown-item">
                                                <a href="{$smarty.const.WWW_TOP}/{$subcat.title}">{$subcat.title}</a>
                                            </li>
                                        {else}
                                            <li class="dropdown-item">
                                                <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                            </li>
                                        {/if}
                                    {/foreach}
                                {elseif $userdata.gameview != "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item">
                                            <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {/if}
                            </ul>
                        </li>
                    </ul>
                {/if}
                {if $parentcat.id == {$catClass::MUSIC_ROOT}}
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" data-close-others="true"
                               data-delay="30">
                                <i class="fa fa-music"></i> {$parentcat.title}
                            </a>
                            <ul class="dropdown-menu">
                                {if $userdata.musicview == "1"}
                                    <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
                                {elseif $userdata.musicview != "1"}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
                                    </li>
                                {/if}
                                <hr>
                                {if $userdata.musicview == "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {elseif $userdata.musicview != "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item">
                                            <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {/if}
                            </ul>
                        </li>
                    </ul>
                {/if}
                {if $parentcat.id == {$catClass::BOOKS_ROOT}}
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" data-close-others="true"
                               data-delay="30">
                                <i class="fa fa-book"></i> Books
                            </a>
                            <ul class="dropdown-menu">
                                {if $userdata.bookview == "1"}
                                    <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
                                {elseif $userdata.bookview != "1"}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
                                    </li>
                                {/if}
                                <hr>
                                {if $userdata.bookview == "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {elseif $userdata.bookview != "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item">
                                            <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {/if}
                            </ul>
                        </li>
                    </ul>
                {/if}
                {if $parentcat.id == {$catClass::XXX_ROOT}}
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" data-close-others="true"
                               data-delay="30">
                                <i class="fa fa-venus-mars"></i> Adult
                            </a>
                            <ul class="dropdown-menu">
                                {if $userdata.xxxview == "1"}
                                    <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
                                {elseif $userdata.xxxview != "1"}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
                                    </li>
                                {/if}
                                <hr>
                                {if $userdata.xxxview == "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        {if $subcat.id == {$catClass::XXX_DVD} OR $subcat.id == {$catClass::XXX_WEBDL} OR $subcat.id == {$catClass::XXX_WMV} OR $subcat.id == {$catClass::XXX_XVID} OR $subcat.id == {$catClass::XXX_X264}}
                                            <li class="dropdown-item">
                                                <a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                            </li>
                                        {else}
                                            <li class="dropdown-item">
                                                <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                            </li>
                                        {/if}
                                    {/foreach}
                                {elseif $userdata.xxxview != "1"}
                                    {foreach $parentcat.subcatlist as $subcat}
                                        <li class="dropdown-item">
                                            <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                        </li>
                                    {/foreach}
                                {/if}
                            </ul>
                        </li>
                    </ul>
                {/if}
                {if $parentcat.id == {$catClass::OTHER_ROOT}}
                    <ul class="navbar-nav mr-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" data-close-others="true"
                               data-delay="30">
                                <i class="fa fa-bolt"></i> Other</a>
                            <ul class="dropdown-menu">
                                <li class="dropdown-item"><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a></li>
                                {foreach $parentcat.subcatlist as $subcat}
                                    <li class="dropdown-item">
                                        <a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
                                    </li>
                                {/foreach}
                            </ul>
                        </li>
                    </ul>
                {/if}
            {/foreach}
        {/if}
        <div class="nav mr-auto">
            {{Form::open(['id' => 'headsearch_form', 'class' => 'form-inline mt-2 mt-md-0', 'url' => 'search', 'method' => 'get'])}}
            <div class="col-md-4">
                <select class="form-control mr-sm-2" id="headcat" name="t">
                    <option class="grouping" value="-1">All</option>
                    {foreach $parentcatlist as $parentcat}
                        <option {if $header_menu_cat == $parentcat.id}selected="selected"{/if} class="grouping"
                                value="{$parentcat.id}">{$parentcat.title}</option>
                        {foreach $parentcat.subcatlist as $subcat}
                            <option {if $header_menu_cat == $subcat.id}selected="selected"{/if}
                                    value="{$subcat.id}">&nbsp;&nbsp;{$subcat.title}</option>
                        {/foreach}
                    {/foreach}
                </select>
            </div>
            <div class="mr-auto">
                <form class="form-inline mt-2 mt-md-0">
                    <input class="form-control mr-sm-2" type="text" placeholder="Search" aria-label="Search" id="headsearch" name="search" value="{if $header_menu_search == ""}{else}{$header_menu_search|escape:"htmlall"}{/if}">
                    <button id="headsearch_go" class="btn btn-outline-success my-2 my-sm-0" type="submit">Search</button>
                </form>
            </div>
            {{Form::close()}}
        </div>
        <ul class="navbar-nav mr-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown"
                   data-close-others="true" data-delay="30" aria-expanded="false">
                    {if $loggedin == "true"}
                    <i class="fa fa-user-circle"></i>
                    {$userdata.username}
                </a>
                <ul class="dropdown-menu">
                    <li class="dropdown-item">
                        <a href="{$smarty.const.WWW_TOP}/cart/index"><i class="fa fa-shopping-basket"></i> My Download Basket</a>
                    </li>
                    <li class="dropdown-item">
                        <a href="{$smarty.const.WWW_TOP}/queue"><i class="fa fa-list-alt"></i> My Queue</a>
                    </li>
                    <li class="dropdown-item">
                        <a href="{$smarty.const.WWW_TOP}/mymovies"><i class="fa fa-film"></i> My Movies</a>
                    </li>
                    <li class="dropdown-item">
                        <a href="{$smarty.const.WWW_TOP}/myshows"><i class="fa fa-television"></i> My Shows</a>
                    </li>
                    <li class="dropdown-item">
                        <a href="{$smarty.const.WWW_TOP}/profileedit"><i class="fa fa-cog fa-spin"></i> Account Settings</a>
                    </li>
                    {if isset($isadmin)}
                        <li class="dropdown-item">
                            <a href="{$smarty.const.WWW_TOP}/admin/index"><i class="fa fa-cogs fa-spin"></i> Admin</a>
                        </li>
                    {/if}
                    <hr>
                    <li class="dropdown-item">
                        <a href="{$smarty.const.WWW_TOP}/profile" class="btn btn-primary btn-outline-info"><i class="fa fa-user"></i> Profile</a>
                    </li>
                    <hr>
                    <li class="dropdown-item">
                        <a href="{$smarty.const.WWW_TOP}/logout" class="btn btn-primary btn-outline-info"><i class="fa fa-unlock-alt"></i> Sign out</a>
                    </li>
                    {/if}
                </ul>
            </li>
        </ul>
        </li>
    </div>
</div>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
                            <div class="container-fluid">
                                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                                    <span class="navbar-toggler-icon"></span>
                                </button>

                                <div class="collapse navbar-collapse" id="navbarCollapse">
                                    {if isset($userdata)}
                                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                                            {foreach $parentcatlist as $parentcat}
                                                {if $parentcat.id == {$catClass::TV_ROOT}}
                                                    <li class="nav-item dropdown">
                                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fa fa-television fa-fw"></i> <span>{$parentcat.title}</span>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}")}}">All TV</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item" href="{{route('series')}}">TV Series</a></li>
                                                            <li><a class="dropdown-item" href="{{route('animelist')}}">Anime Series</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            {foreach $parentcat.categories as $subcat}
                                                                <li><a class="dropdown-item" href="{{url("/browse/TV/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                            {/foreach}
                                                        </ul>
                                                    </li>
                                                {/if}

                                                {if $parentcat.id == {$catClass::MOVIE_ROOT}}
                                                    <li class="nav-item dropdown">
                                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fa fa-film fa-fw"></i> <span>{$parentcat.title}</span>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            {if $userdata.movieview == "1"}
                                                                <li><a class="dropdown-item" href="{{url("/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {else}
                                                                <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {/if}
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item" href="{{route('mymovies')}}">My Movies</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            {if $userdata.movieview == "1"}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {else}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {/if}
                                                        </ul>
                                                    </li>
                                                {/if}

                                                {if $parentcat.id == {$catClass::GAME_ROOT}}
                                                    <li class="nav-item dropdown">
                                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fa fa-gamepad fa-fw"></i> <span>{$parentcat.title}</span>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            {if $userdata.consoleview == "1"}
                                                                <li><a class="dropdown-item" href="{{url("/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {else}
                                                                <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {/if}
                                                            <li><hr class="dropdown-divider"></li>
                                                            {if $userdata.consoleview == "1"}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {else}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {/if}
                                                        </ul>
                                                    </li>
                                                {/if}

                                                {if $parentcat.id == {$catClass::PC_ROOT}}
                                                    <li class="nav-item dropdown">
                                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fa fa-desktop fa-fw"></i> <span>{$parentcat.title}</span>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            {if $userdata.gameview == "1"}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    {if $subcat.id == {$catClass::PC_GAMES}}
                                                                        <li><a class="dropdown-item" href="{{url("/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                    {else}
                                                                        <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                    {/if}
                                                                {/foreach}
                                                            {else}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {/if}
                                                        </ul>
                                                    </li>
                                                {/if}

                                                {if $parentcat.id == {$catClass::MUSIC_ROOT}}
                                                    <li class="nav-item dropdown">
                                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fa fa-music fa-fw"></i> <span>{$parentcat.title}</span>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            {if $userdata.musicview == "1"}
                                                                <li><a class="dropdown-item" href="{{url("/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {else}
                                                                <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {/if}
                                                            <li><hr class="dropdown-divider"></li>
                                                            {if $userdata.musicview == "1"}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {else}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {/if}
                                                        </ul>
                                                    </li>
                                                {/if}

                                                {if $parentcat.id == {$catClass::BOOKS_ROOT}}
                                                    <li class="nav-item dropdown">
                                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fa fa-book fa-fw"></i> <span>Books</span>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            {if $userdata.bookview == "1"}
                                                                <li><a class="dropdown-item" href="{{url("/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {else}
                                                                <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {/if}
                                                            <li><hr class="dropdown-divider"></li>
                                                            {if $userdata.bookview == "1"}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {else}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {/if}
                                                        </ul>
                                                    </li>
                                                {/if}

                                                {if $parentcat.id == {$catClass::XXX_ROOT}}
                                                    <li class="nav-item dropdown">
                                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fa fa-venus-mars fa-fw"></i> <span>Adult</span>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            {if $userdata.xxxview == "1"}
                                                                <li><a class="dropdown-item" href="{{url("/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {else}
                                                                <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            {/if}
                                                            <li><hr class="dropdown-divider"></li>
                                                            {if $userdata.xxxview == "1"}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    {if $subcat.id == {$catClass::XXX_DVD} OR $subcat.id == {$catClass::XXX_WEBDL} OR $subcat.id == {$catClass::XXX_WMV} OR $subcat.id == {$catClass::XXX_XVID} OR $subcat.id == {$catClass::XXX_X264}}
                                                                        <li><a class="dropdown-item" href="{{url("/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                    {else}
                                                                        <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                    {/if}
                                                                {/foreach}
                                                            {else}
                                                                {foreach $parentcat.categories as $subcat}
                                                                    <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                                {/foreach}
                                                            {/if}
                                                        </ul>
                                                    </li>
                                                {/if}

                                                {if $parentcat.id == {$catClass::OTHER_ROOT}}
                                                    <li class="nav-item dropdown">
                                                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="fa fa-bolt fa-fw"></i> <span>Other</span>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-dark">
                                                            <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}")}}">{$parentcat.title}</a></li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            {foreach $parentcat.categories as $subcat}
                                                                <li><a class="dropdown-item" href="{{url("/browse/{$parentcat.title}/{$subcat.title}")}}">{$subcat.title}</a></li>
                                                            {/foreach}
                                                        </ul>
                                                    </li>
                                                {/if}
                                            {/foreach}
                                        </ul>
                                    {/if}

                                    <div class="d-flex align-items-center">
                                        <!-- Search form -->
                                        {{Form::open(['id' => 'headsearch_form', 'class' => 'd-flex align-items-center me-3', 'url' => 'search', 'method' => 'get'])}}
                                            <div class="input-group">
                                                <select class="form-select form-select-sm" id="headcat" name="t" style="max-width: 120px;">
                                                    <option value="-1">All</option>
                                                    {foreach $parentcatlist as $parentcat}
                                                        <option {if $header_menu_cat == $parentcat.id}selected="selected"{/if} class="fw-bold" value="{$parentcat.id}">{$parentcat.title}</option>
                                                        {foreach $parentcat.categories as $subcat}
                                                            <option {if $header_menu_cat == $subcat.id}selected="selected"{/if} value="{$subcat.id}">&nbsp;&nbsp;{$subcat.title}</option>
                                                        {/foreach}
                                                    {/foreach}
                                                </select>
                                                <input class="form-control form-control-sm" type="search" placeholder="Search" aria-label="Search" id="headsearch" name="search" value="{if $header_menu_search == ""}{else}{$header_menu_search|escape:"htmlall"}{/if}">
                                                <button class="btn btn-outline-success btn-sm" type="submit" id="headsearch_go">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </div>
                                        {{Form::close()}}

                                        <!-- User menu -->
                                        {if $loggedin == "true"}
                                            <div class="dropdown">
                                                <a class="btn btn-outline-light btn-sm dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fa fa-user-circle me-1"></i>
                                                    <span>{$userdata.username}</span>
                                                </a>
                                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                                                    <li>
                                                        <a class="dropdown-item" href="{{url("/cart/index")}}">
                                                            <i class="fa fa-shopping-basket fa-fw me-2"></i>My Download Basket
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="{{route('mymovies')}}">
                                                            <i class="fa fa-film fa-fw me-2"></i>My Movies
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="{{route('myshows')}}">
                                                            <i class="fa fa-television fa-fw me-2"></i>My Shows
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="{{route('profileedit')}}">
                                                            <i class="fa fa-cog fa-fw me-2"></i>Account Settings
                                                        </a>
                                                    </li>
                                                    {if isset($isadmin)}
                                                        <li>
                                                            <a class="dropdown-item" href="{{url("/admin/index")}}">
                                                                <i class="fa fa-cogs fa-fw me-2"></i>Admin
                                                            </a>
                                                        </li>
                                                    {/if}
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item" href="{{route('profile')}}">
                                                            <i class="fa fa-user fa-fw me-2"></i>Profile
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="{{route('logout')}}">
                                                            <i class="fa fa-sign-out fa-fw me-2"></i>Sign out
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        {else}
                                            <div class="d-flex">
                                                <a href="{{route('login')}}" class="btn btn-outline-light btn-sm me-2">
                                                    <i class="fa fa-sign-in me-1"></i>Login
                                                </a>
                                                <a href="{{route('register')}}" class="btn btn-outline-success btn-sm">
                                                    <i class="fa fa-user-plus me-1"></i>Register
                                                </a>
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </nav>

                        <style>
                        .dropdown-menu-dark {
                            background-color: #343a40;
                            border-color: rgba(255,255,255,.1);
                        }
                        .dropdown-menu-dark .dropdown-item {
                            color: #dee2e6;
                        }
                        .dropdown-menu-dark .dropdown-item:hover {
                            background-color: rgba(255,255,255,.1);
                        }
                        .navbar .dropdown-toggle::after {
                            vertical-align: middle;
                        }
                        .navbar .nav-link {
                            padding: 0.5rem 1rem;
                            position: relative;
                        }
                        .navbar .nav-link.active:after {
                            content: '';
                            position: absolute;
                            bottom: 0;
                            left: 0;
                            width: 100%;
                            height: 2px;
                            background-color: #fff;
                        }
                        .navbar .nav-link:hover {
                            background-color: rgba(255,255,255,.1);
                            border-radius: 3px;
                        }
                        </style>

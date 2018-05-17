<div id="menucontainer">
	<div class="collapse navbar-collapse nav navbar-nav top-menu">
		{if isset($userdata)}
		{if $loggedin == "true"}
			{foreach $parentcatlist as $parentcat}
				{if $parentcat.id == {$catClass::TV_ROOT}}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="zmdi zmdi-tv-play"></i> {$parentcat.title}
						</a>
						<ul class="dropdown-menu">
							<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">TV</a></li>
							<hr>
							<li><a href="{$smarty.const.WWW_TOP}/series">TV Series</a></li>
							<li><a href="{$smarty.const.WWW_TOP}/anime">Anime Series</a></li>
							<hr>
							{foreach $parentcat.subcatlist as $subcat}
								<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a></li>
							{/foreach}
						</ul>
					</li>
				{/if}
				{if $parentcat.id == {$catClass::MOVIE_ROOT}}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="zmdi zmdi-movie-alt"></i> {$parentcat.title}
						</a>
						<ul class="dropdown-menu">
							{if $userdata.movieview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.movieview != "1"}
								<li>
									<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
								</li>
							{/if}
							<hr>
							<li><a href="{$smarty.const.WWW_TOP}/mymovies">My Movies</a></li>
							<hr>
							{if $userdata.movieview == "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
								{/foreach}
							{elseif $userdata.movieview != "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
								{/foreach}
							{/if}
						</ul>
					</li>
				{/if}
				{if $parentcat.id == {$catClass::GAME_ROOT}}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="zmdi zmdi-xbox"></i> {$parentcat.title}
						</a>
						<ul class="dropdown-menu">
							{if $userdata.consoleview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.consoleview != "1"}
								<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
								</li>
							{/if}
							<hr>
							{if $userdata.consoleview == "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
								{/foreach}
							{elseif $userdata.consoleview != "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
								{/foreach}
							{/if}
						</ul>
					</li>
				{/if}
				{if $parentcat.id == {$catClass::PC_ROOT}}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="zmdi zmdi-keyboard"></i> {$parentcat.title}
						</a>
						<ul class="dropdown-menu">
							{if $userdata.gameview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.gameview != "1"}
								<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
								</li>
							{/if}
							<hr>
							{if $userdata.gameview == "1"}
								{foreach $parentcat.subcatlist as $subcat}
									{if $subcat.id == {$catClass::PC_GAMES}}
										<li><a href="{$smarty.const.WWW_TOP}/{$subcat.title}">{$subcat.title}</a>
										</li>
									{else}
										<li>
											<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
										</li>
									{/if}
								{/foreach}
							{elseif $userdata.gameview != "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
								{/foreach}
							{/if}
						</ul>
					</li>
				{/if}
				{if $parentcat.id == {$catClass::MUSIC_ROOT}}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="zmdi zmdi-audio"></i> {$parentcat.title}
						</a>
						<ul class="dropdown-menu">
							{if $userdata.musicview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.musicview != "1"}
								<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
								</li>
							{/if}
							<hr>
							{if $userdata.musicview == "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a></li>
								{/foreach}
							{elseif $userdata.musicview != "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
								{/foreach}
							{/if}
						</ul>
					</li>
				{/if}
				{if $parentcat.id == {$catClass::BOOKS_ROOT}}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="zmdi zmdi-book"></i> Books
						</a>
						<ul class="dropdown-menu">
                            {if $userdata.bookview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
                            {elseif $userdata.bookview != "1"}
								<li>
									<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
								</li>
                            {/if}
							<hr>
                            {if $userdata.bookview == "1"}
                                {foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
                                {/foreach}
                            {elseif $userdata.bookview != "1"}
                                {foreach $parentcat.subcatlist as $subcat}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
                                {/foreach}
                            {/if}
						</ul>
					</li>
				{/if}
				{if $parentcat.id == {$catClass::XXX_ROOT}}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="zmdi zmdi-male-female"></i> Adult
						</a>
						<ul class="dropdown-menu">
							{if $userdata.xxxview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.xxxview != "1"}
								<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
								</li>
							{/if}
							<hr>
							{if $userdata.xxxview == "1"}
								{foreach $parentcat.subcatlist as $subcat}
									{if $subcat.id == {$catClass::XXX_DVD} OR $subcat.id == {$catClass::XXX_WEBDL} OR $subcat.id == {$catClass::XXX_WMV} OR $subcat.id == {$catClass::XXX_XVID} OR $subcat.id == {$catClass::XXX_X264}}
										<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
										</li>
									{else}
										<li>
											<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
										</li>
									{/if}
								{/foreach}
							{elseif $userdata.xxxview != "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
								{/foreach}
							{/if}
						</ul>
					</li>
				{/if}
				{if $parentcat.id == {$catClass::OTHER_ROOT}}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="zmdi zmdi-thumb-up-down"></i> Other</a>
						<ul class="dropdown-menu">
							<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a></li>
							{foreach $parentcat.subcatlist as $subcat}
								<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a></li>
							{/foreach}
						</ul>
					</li>
				{/if}
			{/foreach}
			<ul class="nav navbar-left">
				<li class="">
					<form class="navbar-form" id="headsearch_form" action="{$smarty.const.WWW_TOP}/search?id="
						  method="get">
						<select class="form-control" id="headcat" name="t">
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
						<input class="form-control" id="headsearch" name="search"
							   value="{if $header_menu_search == ""}{else}{$header_menu_search|escape:"htmlall"}{/if}"
							   placeholder="Search" type="text"/>
						<button id="headsearch_go" type="submit" class="btn btn-success"><i
									class="zmdi zmdi-search"></i></button>
					</form>
				</li>
			</ul>
			<!-- End If logged in -->
		{/if}
		<!-- user dropdown starts -->
		{if $loggedin == "true"}
			<div class="btn-group navbar-right">
				<button class="btn btn-default dropdown-toggle" data-toggle="dropdown">
					<i class="zmdi zmdi-account"></i><span class="hidden-sm hidden-xs"><span
								class="username"> Hi, {$userdata.username}</span></span>
					<span class="caret"></span>
				</button>
				<ul class="dropdown-menu">
					<li><a href="{$smarty.const.WWW_TOP}/profile"><i
									class="zmdi zmdi-account"></i><span> My Profile</span></a></li>
					<li><a href="{$smarty.const.WWW_TOP}/cart/index"><i class="zmdi zmdi-shopping-basket"></i><span> My Download Basket</span></a>
					</li>
					<li><a href="{$smarty.const.WWW_TOP}/queue"><i
									class="zmdi zmdi-cloud-download"></i><span> My Queue</span></a></li>
					<li><a href="{$smarty.const.WWW_TOP}/mymovies"><i
									class="zmdi zmdi-movie-alt"></i><span> My movies</span></a></li>
					<li><a href="{$smarty.const.WWW_TOP}/myshows"><i class="zmdi zmdi-tv-play"></i> My Shows</a></li>
					<li><a href="{$smarty.const.WWW_TOP}/profileedit"><i class="zmdi zmdi-coffee"></i><span> Account Settings</span></a>
					</li>
					{if isset($isadmin)}
						<li><a href="{$smarty.const.WWW_TOP}/admin/index"><i
										class="zmdi zmdi-settings"></i><span> Admin</span></a></li>
					{/if}
					<li><a href="{$smarty.const.WWW_TOP}/logout"><i class="zmdi zmdi-lock-open"></i><span> Logout</span></a>
					</li>
				</ul>
				{else}
				<li><a href="{$smarty.const.WWW_TOP}/login"><i class="zmdi zmdi-lock"></i><span> Login</span></a></li>
				<li><a href="{$smarty.const.WWW_TOP}/register"><i
								class="zmdi zmdi-bookmark-outline"></i><span> Register</span></a></li>
			</div>
		{/if}
		<!-- user dropdown ends -->
	</div>
	{/if}
</div>
</div>

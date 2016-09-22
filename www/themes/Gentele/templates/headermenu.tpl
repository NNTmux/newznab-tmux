<div id="menucontainer" xmlns="http://www.w3.org/1999/html">
	<div class="collapse navbar-collapse nav navbar-nav top-menu">
		{if $loggedin == "true"}
			{if isset($userdata)}
				{foreach $parentcatlist as $parentcat}
					{if $parentcat.id == {$catClass::TV_ROOT}}
						<li class="nav-parent">
							<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
							   data-delay="30">
								<i class="fa fa-television"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
							</a>
							<ul class="dropdown-menu">
								<li><a href="{$smarty.const.WWW_TOP}/browse?t={$parentcat.id}">TV</a></li>
								<hr>
								<li><a href="{$smarty.const.WWW_TOP}/series">TV Series</a></li>
								<li><a href="{$smarty.const.WWW_TOP}/anime">Anime Series</a></li>
								<hr>
								{foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a></li>
								{/foreach}
							</ul>
						</li>
					{/if}
					{if $parentcat.id == {$catClass::MOVIE_ROOT}}
						<li class="nav-parent">
							<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
							   data-delay="30">
								<i class="fa fa-film"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
							</a>
							<ul class="dropdown-menu">
								{if $userdata.movieview == "1"}
									<li><a href="{$smarty.const.WWW_TOP}/movies">{$parentcat.title}</a></li>
								{elseif $userdata.movieview != "1"}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::MOVIE_ROOT}">{$parentcat.title}</a>
									</li>
								{/if}
								<hr>
								<li><a href="{$smarty.const.WWW_TOP}/mymovies">My Movies</a></li>
								<hr>
								{if $userdata.movieview == "1"}
									{foreach $parentcat.subcatlist as $subcat}
										<li><a href="{$smarty.const.WWW_TOP}/movies?t={$subcat.id}">{$subcat.title}</a>
										</li>
									{/foreach}
								{elseif $userdata.movieview != "1"}
									{foreach $parentcat.subcatlist as $subcat}
										<li><a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a>
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
								<i class="fa fa-gamepad"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
							</a>
							<ul class="dropdown-menu">
								{if $userdata.consoleview == "1"}
									<li><a href="{$smarty.const.WWW_TOP}/console">{$parentcat.title}</a></li>
								{elseif $userdata.consoleview != "1"}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::GAME_ROOT}">{$parentcat.title}</a>
									</li>
								{/if}
								<hr>
								{if $userdata.consoleview == "1"}
									{foreach $parentcat.subcatlist as $subcat}
										<li><a href="{$smarty.const.WWW_TOP}/console?t={$subcat.id}">{$subcat.title}</a>
										</li>
									{/foreach}
								{elseif $userdata.consoleview != "1"}
									{foreach $parentcat.subcatlist as $subcat}
										<li><a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a>
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
								<i class="fa fa-gamepad"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
							</a>
							<ul class="dropdown-menu">
								{if $userdata.gameview == "1"}
									<li><a href="{$smarty.const.WWW_TOP}/games">{$parentcat.title}</a></li>
								{elseif $userdata.gameview != "1"}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::PC_ROOT}">{$parentcat.title}</a>
									</li>
								{/if}
								<hr>
								{if $userdata.gameview == "1"}
									{foreach $parentcat.subcatlist as $subcat}
										{if $subcat.id == {$catClass::PC_GAMES}}
											<li>
												<a href="{$smarty.const.WWW_TOP}/games?t={$subcat.id}">{$subcat.title}</a>
											</li>
										{else}
											<li>
												<a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a>
											</li>
										{/if}
									{/foreach}
								{elseif $userdata.gameview != "1"}
									{foreach $parentcat.subcatlist as $subcat}
										<li><a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a>
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
								<i class="fa fa-music"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
							</a>
							<ul class="dropdown-menu">
								{if $userdata.musicview == "1"}
									<li><a href="{$smarty.const.WWW_TOP}/music">{$parentcat.title}</a></li>
								{elseif $userdata.musicview != "1"}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::MUSIC_ROOT}">{$parentcat.title}</a>
									</li>
								{/if}
								<hr>
								{if $userdata.musicview == "1"}
									{foreach $parentcat.subcatlist as $subcat}
										<li><a href="{$smarty.const.WWW_TOP}/music?t={$subcat.id}">{$subcat.title}</a>
										</li>
									{/foreach}
								{elseif $userdata.musicview != "1"}
									{foreach $parentcat.subcatlist as $subcat}
										<li><a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a>
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
								<i class="fa fa-book"></i> Books<i class="fa fa-angle-down"></i>
							</a>
							<ul class="dropdown-menu">
								{if $userdata.bookview == "1"}
									<li><a href="{$smarty.const.WWW_TOP}/books">{$parentcat.title}</a></li>
								{elseif $userdata.bookview != "1"}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::BOOKS_ROOT}">{$parentcat.title}</a>
									</li>
								{/if}
								<hr>
								{foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a></li>
								{/foreach}
							</ul>
						</li>
					{/if}
					{if $parentcat.id == {$catClass::XXX_ROOT}}
						<li class="nav-parent">
							<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
							   data-delay="30">
								<i class="fa fa-venus-mars"></i> Adult<i class="fa fa-angle-down"></i>
							</a>
							<ul class="dropdown-menu">
								{if $userdata.xxxview == "1"}
									<li><a href="{$smarty.const.WWW_TOP}/xxx">{$parentcat.title}</a></li>
								{elseif $userdata.xxxview != "1"}
									<li>
										<a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::XXX_ROOT}">{$parentcat.title}</a>
									</li>
								{/if}
								<hr>
								{if $userdata.xxxview == "1"}
									{foreach $parentcat.subcatlist as $subcat}
										{if $subcat.id == {$catClass::XXX_DVD} OR $subcat.id == {$catClass::XXX_WMV} OR $subcat.id == {$catClass::XXX_XVID} OR $subcat.id == {$catClass::XXX_X264}}
											<li><a href="{$smarty.const.WWW_TOP}/xxx?t={$subcat.id}">{$subcat.title}</a>
											</li>
										{else}
											<li>
												<a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a>
											</li>
										{/if}
									{/foreach}
								{elseif $userdata.xxxview != "1"}
									{foreach $parentcat.subcatlist as $subcat}
										<li><a href="{$smarty.const.WWW_TOP}/browse?t={$subcat.id}">{$subcat.title}</a>
										</li>
									{/foreach}
								{/if}
							</ul>
						</li>
					{/if}
				{/foreach}
				<li class="nav-parent">
					<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true" data-delay="30">
						<i class="fa fa-bolt"></i> Other<i class="fa fa-angle-down"></i></a>
					<ul class="dropdown-menu">
						<li><a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::OTHER_ROOT}">Other</a></li>
						<li><a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::OTHER_MISC}">Misc</a></li>
						<li><a href="{$smarty.const.WWW_TOP}/browse?t={$catClass::OTHER_HASHED}">Hashed</a></li>
					</ul>
				</li>
			{/if}
			<ul class="nav navbar-left">
				<li class="">
					<form class="navbar-form" id="headsearch_form" action="{$smarty.const.WWW_TOP}/search/"
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
						<button id="headsearch_go" type="submit" class="btn btn-success"><i class="fa fa-search"></i>
						</button>
					</form>
				</li>
			</ul>
			<!-- End If logged in -->
		{/if}
		<li class="nav-parent">
			<ul class="nav navbar">
				<li class="">
					<a href="javascript:;" class="user-profile dropdown-toggle" data-toggle="dropdown"
					   data-hover="dropdown" data-close-others="true" data-delay="30" aria-expanded="false">
						{if $loggedin == "true"}
						<img src="{$smarty.const.WWW_THEMES}/shared/images/userimage.png"
							 alt="User Image"> {$userdata.username}
						<span class=" fa fa-angle-down"></span>
					</a>
					<ul class="dropdown-menu dropdown-usermenu navbar-right">
						<li><a href="{$smarty.const.WWW_TOP}/cart"><i class="fa fa-shopping-basket"></i> My
								Download Basket</a>
						</li>
						<li>
							<a href="{$smarty.const.WWW_TOP}/queue"><i class="fa fa-list-alt"></i> My Queue</a>
						</li>
						<li>
							<a href="{$smarty.const.WWW_TOP}/mymovies"><i class="fa fa-film"></i> My Movies</a>
						</li>
						<li><a href="{$smarty.const.WWW_TOP}/myshows"><i class="fa fa-television"></i> My Shows</a>
						</li>
						<li>
							<a href="{$smarty.const.WWW_TOP}/profileedit"><i class="fa fa-cog fa-spin"></i>
								Account Settings</a>
						</li>
						{if isset($isadmin)}
							<li>
								<a href="{$smarty.const.WWW_TOP}/admin"><i class="fa fa-cogs fa-spin"></i> Admin</a>
							</li>
						{/if}
						<li>
							<a href="{$smarty.const.WWW_TOP}/profile" class="btn btn-default btn-flat"><i
										class="fa fa-user"></i> Profile</a>
						</li>
						<li>
							<a href="{$smarty.const.WWW_TOP}/logout" class="btn btn-default btn-flat"><i
										class="fa fa-unlock-alt"></i> Sign out</a>
						</li>
						{else}
						<li><a href="{$smarty.const.WWW_TOP}/login"><i
										class="fa fa-lock"></i><span> Login</span></a></li>
						<li><a href="{$smarty.const.WWW_TOP}/register"><i
										class="fa fa-bookmark-o"></i><span> Register</span></a>
						</li>
						{/if}
					</ul>
				</li>
			</ul>
		</li>
	</div>
</div>

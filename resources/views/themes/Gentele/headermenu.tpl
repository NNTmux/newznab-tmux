<div id="menucontainer" xmlns="http://www.w3.org/1999/html">
	<div class="collapse navbar-collapse nav navbar-nav top-menu">
		{if isset($userdata)}
			{foreach $parentcatlist as $parentcat}
				{if $parentcat.id == {$catClass::TV_ROOT} && $userdata->hasPermissionTo('view tv') == true}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="fa fa-television"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
						</a>
						<ul class="dropdown-menu">
							<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">TV</a></li>
							<hr>
							<li><a href="{$smarty.const.WWW_TOP}/series">TV Series</a></li>
							<li><a href="{$smarty.const.WWW_TOP}/animelist">Anime Series</a></li>
							<hr>
							{foreach $parentcat.subcatlist as $subcat}
								<li><a href="{$smarty.const.WWW_TOP}/browse/TV/{$subcat.title}">{$subcat.title}</a></li>
							{/foreach}
						</ul>
					</li>
				{/if}
				{if $parentcat.id == {$catClass::MOVIE_ROOT} && $userdata->hasPermissionTo('view movies') == true}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="fa fa-film"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
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
				{if $parentcat.id == {$catClass::GAME_ROOT} && $userdata->hasPermissionTo('view pc') == true}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="fa fa-gamepad"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
						</a>
						<ul class="dropdown-menu">
							{if $userdata.consoleview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.consoleview != "1"}
								<li>
									<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
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
				{if $parentcat.id == {$catClass::PC_ROOT} && $userdata->hasPermissionTo('view pc') == true}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="fa fa-gamepad"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
						</a>
						<ul class="dropdown-menu">
							{if $userdata.gameview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.gameview != "1"}
								<li>
									<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
								</li>
							{/if}
							<hr>
							{if $userdata.gameview == "1"}
								{foreach $parentcat.subcatlist as $subcat}
									{if $subcat.id == {$catClass::PC_GAMES}}
										<li>
											<a href="{$smarty.const.WWW_TOP}/{$subcat.title}">{$subcat.title}</a>
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
				{if $parentcat.id == {$catClass::MUSIC_ROOT} && $userdata->hasPermissionTo('view audio') == true}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="fa fa-music"></i> {$parentcat.title}<i class="fa fa-angle-down"></i>
						</a>
						<ul class="dropdown-menu">
							{if $userdata.musicview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.musicview != "1"}
								<li>
									<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
								</li>
							{/if}
							<hr>
							{if $userdata.musicview == "1"}
								{foreach $parentcat.subcatlist as $subcat}
									<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a>
									</li>
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
				{if $parentcat.id == {$catClass::BOOKS_ROOT} && $userdata->hasPermissionTo('view books') == true}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="fa fa-book"></i> Books<i class="fa fa-angle-down"></i>
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
				{if $parentcat.id == {$catClass::XXX_ROOT} && $userdata->hasPermissionTo('view adult') == true}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="fa fa-venus-mars"></i> Adult<i class="fa fa-angle-down"></i>
						</a>
						<ul class="dropdown-menu">
							{if $userdata.xxxview == "1"}
								<li><a href="{$smarty.const.WWW_TOP}/{$parentcat.title}">{$parentcat.title}</a></li>
							{elseif $userdata.xxxview != "1"}
								<li>
									<a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a>
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
				{if $parentcat.id == {$catClass::OTHER_ROOT} && $userdata->hasPermissionTo('view other') == true}
					<li class="nav-parent">
						<a href="#" data-toggle="dropdown" data-hover="dropdown" data-close-others="true"
						   data-delay="30">
							<i class="fa fa-bolt"></i> Other<i class="fa fa-angle-down"></i></a>
						<ul class="dropdown-menu">
							<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}">{$parentcat.title}</a></li>
							{foreach $parentcat.subcatlist as $subcat}
							<li><a href="{$smarty.const.WWW_TOP}/browse/{$parentcat.title}/{$subcat.title}">{$subcat.title}</a></li>
							{/foreach}
						</ul>
					</li>
				{/if}
			{/foreach}
		{/if}
		<ul class="nav navbar-left">
			<li class="">
                {{Form::open(['id' => 'headsearch_form', 'class' => 'navbar-form', 'url' => 'search', 'method' => 'get'])}}
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
					<button id="headsearch_go" type="submit" class="btn btn-success"><i class="fab fa-searchengin"></i>
					</button>
				{{Form::close()}}
			</li>
		</ul>

		<li class="nav-parent">
			<ul class="nav navbar">
				<li class="">
					<a href="javascript:;" class="user-profile dropdown-toggle" data-toggle="dropdown"
					   data-hover="dropdown" data-close-others="true" data-delay="30" aria-expanded="false">
						{if $loggedin == "true"}
						<img src="{$smarty.const.WWW_ASSETS}/images/userimage.png"
							 alt="User Image"> {$userdata.username}
						<span class=" fa fa-angle-down"></span>
					</a>
					<ul class="dropdown-menu dropdown-usermenu navbar-right">
						<li><a href="{$smarty.const.WWW_TOP}/cart/index"><i class="fa fa-shopping-basket"></i> My
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
								<a href="{$smarty.const.WWW_TOP}/admin/index"><i class="fa fa-cogs fa-spin"></i>
									Admin</a>
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
						{/if}
					</ul>
				</li>
			</ul>
		</li>
	</div>
</div>

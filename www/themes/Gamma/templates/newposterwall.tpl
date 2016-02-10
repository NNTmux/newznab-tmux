<div class="category" style="padding-bottom:20px;">
	{if $error}
		<h2>{$error}</h2>
	{else}
		<h2 class="main-title">
			<a class="see-more" href="{$smarty.const.WWW_TOP}/{$goto}">see more &raquo;</a>
			The <strong>newest releases</strong> for
			<strong>
				<select name="MySelect" id="MySelect"
						onchange="window.location='{$smarty.const.WWW_TOP}/newposterwall?t=' + this.value;">
					{foreach $types as $newtype}
						<option {if $type eq $newtype}selected="selected"{/if} value="{$newtype}">
							{$newtype}
						</option>
					{/foreach}
				</select>
			</strong>
		</h2>
		<div class="main-wrapper">
			<div class="main-content">
				<!-- library -->
				<div class="library-wrapper">
					{foreach $newest as $result}
						<div
								{if $type eq 'Console'}
									class="library-console"
								{elseif $type eq 'Movies'}
									class="library-show"
								{elseif $type eq 'XXX'}
									class="library-show"
								{elseif $type eq 'Audio'}
									class="library-music"
								{elseif $type eq 'Books'}
									class="library-show"
								{elseif $type eq 'PC'}
									class="library-games"
								{elseif $type eq 'TV'}
									class="library-show"
								{elseif $type eq 'Anime'}
									class="library-show"
								{/if}
								>
							<div class="poster">
								<a class="titleinfo" title="{$result.guid}"
								   href="{$smarty.const.WWW_TOP}/details/{$result.guid}">
									{if $type eq 'Console'}
										<img width="130px" alt=""
											 src="{$smarty.const.WWW_TOP}/covers/console/{$result.consoleinfoid}.jpg"/>
									{elseif $type eq 'Movies'}
										<img width="140px" height="205px" alt=""
											 src="{$smarty.const.WWW_TOP}/covers/movies/{$result.imdbid}-cover.jpg"/>
									{elseif $type eq 'XXX'}
										<img width="140px" height="205px" alt=""
											 src="{$smarty.const.WWW_TOP}/covers/xxx/{$result.xxxinfo_id}-cover.jpg"/>
									{elseif $type eq 'Audio'}
										<img height="250px" width="250px" alt=""
											 src="{$smarty.const.WWW_TOP}/covers/music/{$result.musicinfoid}.jpg"/>
									{elseif $type eq 'Books'}
										<img height="140px" width="205px" alt=""
											 src="{$smarty.const.WWW_TOP}/covers/book/{$result.bookinfoid}.jpg"/>
									{elseif $type eq 'PC'}
										<img height="130px" width="130px" alt=""
											 src="{$smarty.const.WWW_TOP}/covers/games/{$result.gamesinfo_id}.jpg"/>
									{elseif $type eq 'TV'}
										<img height="130px" width="130px" alt=""
											 src="{$smarty.const.WWW_TOP}/covers/tvshows/{$result.videos_id}.jpg"/>
									{elseif $type eq 'Anime'}
										<img width="130px" height="130px" alt=""
											 src="{$smarty.const.WWW_TOP}/covers/anime/{$result.anidbid}.jpg"/>
									{/if}
								</a>
							</div>
							<div class="rating-pod" id="guid{$result.guid}">
								<div class="icons divlink col-lg-4">
									<span class="btn btn-hover btn-default btn-sm icon_nzb text-muted"><a title="Download Nzb"
																										  href="{$smarty.const.WWW_TOP}/getnzb/{$result.guid}">
											<i class="fa fa-cloud-download"></i></a>
									</span>
									<span class="btn btn-hover btn-default btn-sm icon_cart text-muted" title="Send to my Download Basket"><i class="fa fa-shopping-basket"></i></span>
									{if isset($sabintegrated)}
										<span class="btn btn-hover btn-default btn-sm icon_sab text-muted" title="Send to my Queue"><i class="fa fa-share"></i></span>
									{/if}
								</div>
							</div>
							<a class="plays" href="#"></a>
						</div>
					{/foreach}
				</div>
			</div>
		</div>
	{/if}
</div>

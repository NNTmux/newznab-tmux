
<h1>{$page->title}</h1>

{if $topgrabs|count > 0}
	<h2>Top Grabbers</h2>

	<table style="width:100%;margin-top:10px;" class="data highlight">
		<tr>
			<th>User</th>
			<th>Grabs</th>
		</tr>

		{foreach from=$topgrabs item=result}
			<tr class="{cycle values=",alt"}">
				<td width="75%"><a href="{$smarty.const.WWW_TOP}/user-edit.php?id={$result.id}">{$result.username}</a></td>
				<td>{$result.grabs}</td>
			</tr>
		{/foreach}

	</table>

	<br/><br/>
{/if}

<h2>Signups</h2>

<table style="width:100%;margin-top:10px;" class="data highlight Sortable">
	<tr>
		<th>Month</th>
		<th>Signups</th>
	</tr>

	{foreach from=$usersbymonth item=result}
		{assign var="totusers" value=$totusers+$result.num}
		<tr class="{cycle values=",alt"}">
			<td width="75%">{$result.mth}</td>
			<td>{$result.num}</td>
		</tr>
	{/foreach}
	<tr><td><strong>Total</strong></td><td><strong>{$totusers}</strong></td></tr>
</table>

<br/><br/>

<h2>Users by Role</h2>

<table style="width:100%;margin-top:10px;" class="data highlight Sortable">
	<tr>
		<th>Role</th>
		<th>Users</th>
	</tr>

	{foreach from=$usersbyrole item=result}
		{assign var="totrusers" value=$totrusers+$result.num}
		<tr class="{cycle values=",alt"}">
			<td width="75%">{$result.name}</td>
			<td>{$result.num}</td>
		</tr>
	{/foreach}
	<tr><td><strong>Total</strong></td><td><strong>{$totrusers}</strong></td></tr>
</table>

<br/><br/>

<h2>Users by Hosthash</h2>

<table style="width:100%;margin-top:10px;" class="data highlight Sortable">
	<tr>
		<th>Hosthash</th>
		<th>User IDs (Edit)</th>
		<th>User Names (Profiles)</th>
	</tr>

	{foreach from=$usersbyhosthash item=result}
		<tr class="{cycle values=",alt"}">

			<td width="25%">{$result.hosthash}</td>
			<td>
			{assign var="usersplits" value=","|explode:$result.user_string}
			{foreach from=$usersplits item=usersplit}
				<a href="{$smarty.const.WWW_TOP}/user-edit.php?id={$usersplit}">{$usersplit}</a>
			{/foreach}
			</td>
			<td>
			{assign var="usernsplits" value=","|explode:$result.user_names}
			{foreach from=$usernsplits item=usernsplit}
				<a href="{$smarty.const.WWW_TOP}/../profile?name={$usernsplit}">{$usernsplit}</a>
			{/foreach}
			</td>
		</tr>
	{/foreach}
</table>

<br/><br/>

<h2>Access by Date</h2>

<table style="width:100%;margin-top:10px;" class="data highlight Sortable">
	<tr>
		<th>Type</th>
		<th>Last Day</th>
		<th>2-7 Days</th>
		<th>8-30 Days</th>
		<th>1-3 Months</th>
		<th>3-6 Months</th>
		<th>+6 Months</th>
	</tr>

	{foreach from=$loginsbymonth item=result}
		<tr class="{cycle values=",alt"}">
			<td width="75%">{$result.type}</td>
			<td>{$result.1day}</td>
			<td>{$result.7day}</td>
			<td>{$result.1month}</td>
			<td>{$result.3month}</td>
			<td>{$result.6month}</td>
			<td>{$result.12month}</td>
		</tr>
	{/foreach}
</table>

<br/><br/>

{if $topdownloads|count > 0}
	<h2>Top Downloads</h2>

	<table style="width:100%;margin-top:10px;" class="data highlight">
		<tr>
			<th>Release</th>
			<th>Grabs</th>
			<th>Days Ago</th>
		</tr>

		{foreach from=$topdownloads item=result}
			<tr class="{cycle values=",alt"}">
				<td width="75%"><a href="{$smarty.const.WWW_TOP}/../details/{$result.guid}">{$result.searchname|escape:"htmlall"|replace:".":" "}</a>
				{if $isadmin}<a href="{$smarty.const.WWW_TOP}/release-edit.php?id={$result.id}">[Edit]</a>{/if}</td>
				<td>{$result.grabs}</td>
				<td>{$result.adddate|timeago}</td>
			</tr>
		{/foreach}

	</table>

	<br/><br/>
{/if}

<h2>Releases Added In Last 7 Days</h2>

<table style="width:100%;margin-top:10px;" class="data highlight">
	<tr>
		<th>Category</th>
		<th>Releases</th>
	</tr>

	{foreach from=$recent item=result}
		<tr class="{cycle values=",alt"}">
			<td width="75%">{$result.title}</td>
			<td>{$result.count}</td>
		</tr>
	{/foreach}

</table>

<br/><br/>

{if $topcomments|count > 0}
	<h2>Top Comments</h2>

	<table style="width:100%;margin-top:10px;" class="data highlight">
		<tr>
			<th>Release</th>
			<th>Comments</th>
			<th>Days Ago</th>
		</tr>

		{foreach from=$topcomments item=result}
			<tr class="{cycle values=",alt"}">
				<td width="75%"><a href="{$smarty.const.WWW_TOP}/../details/{$result.guid}/#comments">{$result.searchname|escape:"htmlall"|replace:".":" "}</a></td>
				<td>{$result.comments}</td>
				<td>{$result.adddate|timeago}</td>
			</tr>
		{/foreach}

	</table>
{/if}
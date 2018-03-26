<div class="well well-sm">
	<h1>{$page->title}</h1>
	<p>This page is used for testing regex for getting release names from usenet subject.
		<br/>Maximum releases to display will limit the amount of results displayed on the page. 0 for no limit.
		<br/>Query limit will limit the amount of releases to select from MySQL (setting this high can be very slow). 0
		for no limit.
	</p>

	<form name="search" action="" method="post" style="margin-bottom:5px;">
		{{csrf_field()}}
		<table class="input data table table-striped responsive-utilities jambo-table">
			<tr>
				<td><label for="group" style="padding-right:1px">Group:</label>
				<td>
					<input id="group" type="text" name="group" value="{$group|htmlentities}" size="20"/><br/>
					<div class="hint">
						Regex to match against a group.<br/>
						Delimiters are already added.<br/>
						An example of matching a group: alt.binaries.example
					</div>
				</td>
			</tr>
			<tr>
				<td><label for="regex" style="padding-right:1px">Regex:</label>
				<td>
					<input id="regex" type="text" name="regex" value="{$regex|htmlentities}" size="100"/><br/>
					<div class="hint">
						The regex to use when trying to name a release using the usenet subject.<br/>
						The regex delimiters are not added, you MUST add them. See <a
								href="http://php.net/manual/en/regexp.reference.delimiters.php">this</a> page.<br/>
						To make the regex case insensitive, add i after the last delimiter.<br/>
						You MUST include at least one regex capture group.<br/>
						You MUST name your regex capture groups (the ones you want included).<br/>
						The named capture groups will be concatenated into a string.<br/>
						Capture groups are sorted alphabetically (by capture group name) when concatenating the
						string.<br/>
						Capture groups named 'reqid' and 'parts' are ignored.
					</div>
				</td>
			</tr>
			<tr>
				<td><label for="showlimit" style="padding-right:7px">Maximum releases to display:</label>
				<td>
					<input id="showlimit" type="text" name="showlimit" value="{$showlimit}" size="8"/><br/>
				</td>
			</tr>
			<tr>
				<td><label for="querylimit" style="padding-right:7px">Query limit:</label>
				<td>
					<input id="querylimit" type="text" name="querylimit" value="{$querylimit}" size="8"/><br/>
				</td>
			</tr>
			<tr>
				<td></td>
				<td>
					<input class="btn btn-default" type="submit" value="Test"/>
				</td>
			</tr>
		</table>
	</form>
	{if isset($data)}
		<table style="margin-top:10px;" class="data table table-striped responsive-utilities jambo-table Sortable">
			<tr>
				<th>Release id</th>
				<th>Usenet Subject</th>
				<th>Old Search Name</th>
				<th>New Search Name</th>
			</tr>
			{foreach from=$data key=id item=names}
				<tr id="row-{$id}" class="{cycle values=",alt"}">
					<td>{$id}</td>
					<td>{$names.subject}</td>
					<td>{$names.old_name}</td>
					<td>{$names.new_name}</td>
				</tr>
			{/foreach}
		</table>
	{/if}
</div>

<div class="well well-sm">
	<h1 xmlns="http://www.w3.org/1999/html">{$title}</h1>
	{if isset($error) && $error != ''}
		<div class="error">{$error}</div>
	{/if}
	<a class="btn btn-success" href="{$smarty.const.WWW_TOP}/admin/release_naming_regexes-list"><i
				class="fa fa-arrow-left"></i> Go back</a>
	<form action="release_naming_regexes-edit?action=submit" method="POST">
		{{csrf_field()}}
		<table class="input data table table-striped responsive-utilities jambo-table">
			<tr>
				<td><label for="group_regex">Group:</label></td>
				<td>
					<input type="hidden" name="id" value="{if isset($regex.id)}{$regex.id}{else}{/if}"/>
					<input type="text" id="group_regex" name="group_regex"
						   value="{if isset($regex.group_regex)}{$regex.group_regex|escape:html}{else}{/if}"/>
					<div class="hint">
						Regex to match against a group or multiple groups.<br/>
						Delimiters are already added, and PCRE_CASELESS is added after for case insensitivity.<br/>
						An example of matching a single group: alt\.binaries\.example<br/>
						An example of matching multiple groups: alt\.binaries.*
					</div>
				</td>
			</tr>
			<tr>
				<td><label for="regex">Regex:</label></td>
				<td>
					<textarea id="regex"
							  name="regex">{if isset($regex.regex)}{$regex.regex|escape:html}{else}{/if}</textarea>
					<div class="hint">
						The regex to use when trying to name a release using the usenet subject.<br/>
						The regex delimiters are not added, you MUST add them. See <a
								href="http://php.net/manual/en/regexp.reference.delimiters">this</a> page.<br/>
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
				<td><label for="description">Description:</label></td>
				<td>
					<textarea id="description"
							  name="description">{if isset($regex.description)}{$regex.description|escape:html}{else}{/if}</textarea>
					<div class="hint">
						Description for this regex.<br/>
						You can include an example usenet subject this regex would match on.
					</div>
				</td>
			</tr>
			<tr>
				<td><label for="ordinal">Ordinal:</label></td>
				<td>
					<input class="ordinal" id="ordinal" name="ordinal" type="text"
						   value="{if isset($regex.ordinal)}{$regex.ordinal}{else}{/if}"/>
					<div class="hint">
						The order to run this regex in.<br/>
						Must be a number, 0 or higher.<br/>
						If multiple regex have the same ordinal, MySQL will randomly sort them.
					</div>
				</td>
			</tr>
			<tr>
				<td><label for="status">Active:</label></td>
				<td>
					{html_radios id="status" name='status' values=$status_ids output=$status_names selected=$regex.status separator='<br />'}
					<div class="hint">Only active regex are used during the collection matching process.</div>
				</td>
			</tr>
			<tr>
				<td></td>
				<td>
					<input class="btn btn-default" type="submit" value="Save"/>
				</td>
			</tr>
		</table>
	</form>
</div>

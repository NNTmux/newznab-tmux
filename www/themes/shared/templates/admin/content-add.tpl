<div class="well well-sm">
	<h1>{$page->title}</h1>
<a class="btn btn-success" href="{$smarty.const.WWW_TOP}/content-list.php"><i class="fa fa-arrow-left"></i> Go back</a>
	<form action="{$SCRIPT_NAME}?action=submit" method="POST">
		<table class="input data table table-striped responsive-utilities jambo-table">
			<tr>
				<td><label for="title">Title:</label></td>
				<td>
					<input type="hidden" name="id" {if isset($content)}value="{$content->id}"{else}{/if}"/>
					<input id="title" class="long" name="title" type="text" {if isset($content)}value="{$content->title}"{else}{/if}/>
				</td>
			</tr>
			<tr>
				<td><label for="url">Url:</label></td>
				<td>
					<input id="url" class="long" name="url" type="text" {if isset($content)}value="{$content->url}"{else}{/if}/>
				</td>
			</tr>
			<tr>
				<td><label for="body">Body:</label></td>
				<td>
					<textarea id="body" name="body">{if isset($content)}{$content->body}{else}{/if}</textarea>
				</td>
			</tr>
			<tr>
				<td><label for="metadescription">Meta Description:</label></td>
				<td>
					<textarea id="metadescription" name="metadescription">{if isset($content)}{$content->metadescription}{else}{/if}</textarea>
				</td>
			</tr>
			<tr>
				<td><label for="metakeywords">Meta Keywords:</label></td>
				<td>
					<textarea id="metakeywords" name="metakeywords">{if isset($content)}{$content->metakeywords}{else}{/if}</textarea>
				</td>
			</tr>
			<tr>
				<td><label for="contenttype">Content Type:</label></td>
				<td>
					{html_options id="contenttype" name='contenttype' options=$contenttypelist selected=$content->contenttype}
				</td>
			</tr>
			<tr>
				<td><label for="role">Visible To:</label></td>
				<td>
					{html_options id="role" name='role' options=$rolelist selected=$content->role}
					<div class="hint">Only appropriate for articles and useful links</div>
				</td>
			</tr>
			<tr>
				<td><label for="showinmenu">Show In Menu:</label></td>
				<td>
					{html_radios id="showinmenu" name='showinmenu' values=$yesno_ids output=$yesno_names selected=$content->showinmenu separator='<br />'}
				</td>
			</tr>
			<tr>
				<td><label for="status">Status:</label></td>
				<td>
					{html_radios id="status" name='status' values=$status_ids output=$status_names selected=$content->status separator='<br />'}
				</td>
			</tr>
			<tr>
				<td><label for="ordinal">Ordinal:</label></td>
				<td>
					<input id="ordinal" name="ordinal" type="text" {if isset($content)}value="{$content->ordinal}"{else}{/if}/>
					<div class="hint">If you set the ordinal = 1, then a all ordinals greater than 0 will be renumbered.
						This allows new content to be at the top without having to renumber all previous content.<br/>If
						you set ordinal = 0, it will be at the top, sorted by ID(order added)
					</div>
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

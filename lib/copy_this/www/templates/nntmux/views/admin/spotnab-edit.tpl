 
<h1>{$page->title}</h1>

<form action="{$SCRIPT_NAME}?action=submit" method="POST">

<table class="input">

<tr>
	<td>Name:</td>
	<td>
		<input type="hidden" name="id" value="{$source.ID}" />
		<input name="description" type="text" value="{$source.description}" />
		<div class="hint">A name or description for the source.</div>		
	</td>
</tr>

<tr>
	<td>Poster Username:</td>
	<td>
		<input name="username" type="text" value="{$source.username}" />
		<div class="hint">The username part of the poster. eg nntp</div>		
	</td>
</tr>
<tr>
	<td>Poster E-mail:</td>
	<td>
		<input name="useremail" type="text" value="{$source.useremail}" />
		<div class="hint">The email part of the poster. eg spot@nntp.com</div>		
	</td>
</tr>
<tr>
	<td>Usenet Group:</td>
	<td>
		<input name="usenetgroup" type="text" value="{$source.usenetgroup}" />
		<div class="hint">Group to search when looking up posts for this source.</div>		
	</td>
</tr>
<tr>
	<td>Public Key:</td>
	<td>
		<textarea class="autosize" name="publickey">{$source.publickey|escape:html}</textarea>
		<div class="hint">Public Key needed to decode the posts specific for this source.</div>		
	</td>
</tr>

<tr>
	<td></td>
	<td>
		<input type="submit" value="Save" />
	</td>
</tr>

</table>

</form>

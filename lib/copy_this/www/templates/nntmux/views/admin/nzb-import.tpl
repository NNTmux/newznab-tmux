 
<h1>{$page->title}</h1>

<p>
Import nzbs via the command line or browser into the system.
</p>
<ul>
<li>If you are importing a large number of nzb files, run the script /misc/update_scripts/import.php from the command line and pass in the folder path as the first argument.</li>
<li>Groups contained in the nzbs should be added to the site before the import is run, they do not have to be active.</li>
<li>Your www user will need write permission to the /nzbfiles folder in order to import the nzb.</li>
</ul>


<fieldset>
<legend>Import From Browser</legend>

<form action="{$SCRIPT_NAME}" method="POST" enctype="multipart/form-data">

<table class="input">

<tr>
	<td width="100"><label for="uploadedfiles[]">File</label>:</td>
	<td>
		<input name="uploadedfiles[]" type="file" class="multi accept-nzb"/>
		<div class="hint">Select one or more .nzb files.</div>
	</td>
</tr>

<tr style="display:none;">
	<td><label for="usefilename">Use Filename</label>:</td>
	<td>
		<input type="checkbox" name="usefilename" />
		<div class="hint">Use the nzb's filename as the release name. This will bypass the release regex process.</div>
	</td>
</tr>

<tr>
	<td></td>
	<td>
		<input type="submit" value="Import" />
	</td>
</tr>

</table>

</fieldset>


{if $output != ""}
	<div>
		<h1>Import Results</h1>
		{$output}
	</div>
{/if}
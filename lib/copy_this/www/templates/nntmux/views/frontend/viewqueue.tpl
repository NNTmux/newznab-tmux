<div class="page-header">
	<h1>{$page->title}</h1>
</div>

<p>The following items are currently being download at <a href="{$sabserver|escape:"htmlall"}">{$sabserver|escape:"htmlall"}</a>. {if $page->site->sabintegrationtype == 2}Edit queue settings in <a href="{$smarty.const.WWW_TOP}/profileedit">your profile</a>.{/if}</p>

<div class="sab_queue"></div>

<br/><br/>

{if $showhistory}<div class="sab_history"></div>{/if}

<script type="text/javascript">
    getQueue();
    {if $showhistory}getHistory();{/if}
</script>


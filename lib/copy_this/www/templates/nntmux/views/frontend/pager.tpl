{assign var="pages" value=($pagertotalitems/$pageritemsperpage)|roundup}
{assign var="currentpage" value=($pageroffset+$pageritemsperpage)/$pageritemsperpage}
{assign var="upperhalfwaypoint" value=((($pages-$currentpage)/2)|roundup)+$currentpage}
 
{if $pages > 1}
{strip} 
<div class="pager">
	{if $currentpage > 1}
		<a title="Goto page 1" href="{$pagerquerybase}0{$pagerquerysuffix}">1</a>&nbsp;
	{/if}

	{if $currentpage > 3}
		<span class="step">...</span>
	{/if}

	{if $currentpage > 2}
		<a title="Goto page {$currentpage-1}" href="{$pagerquerybase}{$pageroffset-$pageritemsperpage}{$pagerquerysuffix}">{$currentpage-1}</a>&nbsp;
	{/if}	

	<span class="current" title="Current page {$currentpage}">{$currentpage}</span>

	{if ($currentpage+1) < $pages}
		&nbsp;<a title="Goto page {$currentpage+1}" href="{$pagerquerybase}{$pageroffset+$pageritemsperpage}{$pagerquerysuffix}">{$currentpage+1}</a>
	{/if}	

	{if ($currentpage+1) < ($pages-1) && ($currentpage+2) < $upperhalfwaypoint}
		<span class="step">&nbsp;...</span>
	{/if}	

	{if $upperhalfwaypoint != $pages && $upperhalfwaypoint != ($currentpage+1)}
		&nbsp;<a title="Goto page {$upperhalfwaypoint}" href="{$pagerquerybase}{$upperhalfwaypoint*$pageritemsperpage}{$pagerquerysuffix}">{$upperhalfwaypoint}</a>
	{/if}	

	{if ($upperhalfwaypoint+1) < $pages}
		<span class="step">&nbsp;...</span>
	{/if}	

	{if $pages > $currentpage}
		&nbsp;<a title="Goto page {$pages}" href="{$pagerquerybase}{($pages*$pageritemsperpage)-$pageritemsperpage}{$pagerquerysuffix}">{$pages}</a>
	{/if}			

</div>
{/strip}
{/if}
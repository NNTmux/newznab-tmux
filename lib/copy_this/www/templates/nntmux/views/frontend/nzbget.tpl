<h1>{$page->title}</h1>
{if $weHasNZBget}
<div id="nzbget-error" style="display: none; background-color: #767A9E; text-align: center; padding: 5px; color: #eee"></div>
<div id="nzbget">
</div>
{literal}
<script type="text/javascript">

    var timer = 0;
    function getOverview()
    {
        $.ajax
        ({
            url: WWW_TOP + '/nzbget?getOverview&isAjax',
            cache: false
        }).done(function(html)
        {
            $("#nzbget").html(html);
            timer = setTimeout(getOverview, 2500);
        }).fail(function(response)
        {
            $('#nzbget').hide();
            $('#nzbget-error').show();
            $('#nzbget-error').html(response.responseText);
            clearTimeout(timer);
        });
    }
    
    getOverview();
    
</script>
{/literal}
{else}
<p>Make sure you've entered API key and server URL under profile settings.</p>
{/if}
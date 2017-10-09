{if !empty($sitekey)}
	<script type="text/javascript"
			src="https://www.google.com/recaptcha/api.js?hl=en">
	</script>
		<form action="?" method="POST">
			<div class="g-recaptcha" data-sitekey="{$sitekey}"></div>
		</form>
	<br/>
{/if}

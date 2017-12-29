{if !empty($sitekey)}
	<form action="?" method="POST">
		<?php echo $captcha->display(); ?>
		<button type="submit">Submit</button>
	</form>
{/if}

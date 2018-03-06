<div class="col-md-4 offset3">
  <h1>Login</h1>
  <br>
	<form class="form-horizontal" action="login" method="post">
		<input type="hidden" name="_token" value="{$csrf_token}">
		{if isset($redirect)}
			<input type="hidden" name="redirect" value="{$redirect|escape:"htmlall"}" />
		{/if}
		<table class="data">
		{if $error != ''}
			<div class="alert alert-error">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				  <h4>Error!</h4>
				  {$error}
			</div>
		{/if}
		<input class="col-md-12" type="text" id="username prependedInput" value="{$username}" name="username" placeholder="Username or email" style="margin-bottom:5px;"><br />
		<input class="col-md-12" id="password" name="password" type="password" placeholder="Password" style="margin-bottom:20px;"><br />
		<input id="rememberme" {if $rememberme == 1}checked="checked"{/if} name="rememberme" type="checkbox"/> <span class="help-inline" style="vertical-align:sub;">Remember me </span>
			<div class="pull-right"><a href="{$serverroot}forgottenpassword" class="text-center">I forgot my password</a></div>
			<div>
			{$page->smarty->fetch('captcha.tpl')}
			</div>
		<button type="submit" class="btn btn-success pull-right">Login</button>
		</table>
	</form>
</div>

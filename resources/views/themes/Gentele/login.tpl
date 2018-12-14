<div class="well well-sm">
	{if isset($error) && $error != ''}
		<div class="alert alert-danger">{$error}</div>
	{/if}
	{if isset($notice) && $notice != ''}
		<div class="alert alert-info">{$notice}</div>
	{/if}
	<div class="login-box">
		<div class="login-logo">
			<a href="{$serverroot}"><b>{$site->title}</b></a>
		</div><!-- /.login-logo -->
		<div class="login-box-body">
			<p class="login-box-msg">Please sign in to access the site</p>
			{{Form::open(['url' => 'login'])}}
				<div class="form-group has-feedback">
					<input id="username" name="username" type="text" class="form-control"
						   placeholder="Username or email"/>
					<span class="fas fa-envelope form-control-feedback"></span>
				</div>
				<div class="form-group has-feedback">
					<input id="password" name="password" type="password" class="form-control" placeholder="Password"/>
					<span class="fas fa-lock form-control-feedback"></span>
				</div>
				<div class="row">
					<div class="col-8">
						<div class="checkbox icheck">
							<label>
								<input id="rememberme" {if isset($rememberme) && $rememberme == 1}checked="checked"{/if}
									   name="rememberme" type="checkbox"> Remember Me
							</label>
							<hr>
                            {if {config('captcha.enabled')} == 1 && !empty({config('captcha.sitekey')}) && !empty({config('captcha.secret')})}
								{NoCaptcha::display()}{NoCaptcha::renderJs()}
							{/if}
						</div>
					</div><!-- /.col -->
					<div class="col-4">
						<button type="submit" class="btn btn-success btn-block btn-flat">Sign In</button>

					</div><!-- /.col -->
				</div>
			{{Form::close()}}

			<a href="{$smarty.const.WWW_TOP}/forgottenpassword" class="text-center">I forgot my password</a><br>
			<a href="{$smarty.const.WWW_TOP}/register" class="text-center">Register a new membership</a>
		</div>
	</div>
</div>

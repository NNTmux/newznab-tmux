<div class="well well-sm">
	{if isset($error) && $error != ''}
		<div class="alert alert-danger">{$error}</div>
	{/if}
	{if isset($notice) && $notice != ''}
		<div class="alert alert-info">{$notice}</div>
	{/if}
	{if isset($sent) && $sent != ''}
		<div class="alert alert-info">A link to reset your password has been sent to your e-mail account.</div>
	{/if}
	{if $showregister == 1}
		<div class="register-box">
			<div class="register-logo">
				<a href="{$serverroot}"><b>{$site->title}</b></a>
			</div>
			<div class="register-box-body">
				<p class="login-box-msg">Register a new membership</p>
                {{Form::open(['url' => "register?action=submit{$invite_code_query}"])}}
					<div class="form-group has-feedback">
						<input autocomplete="off" id="username" name="username" value="{$username}" type="text"
							   class="form-inline" placeholder="Username"/>
						<span class="glyphicon glyphicon-user form-control-feedback"></span>
						<div class="hint">Should be at least five characters and start with a letter.</div>
					</div>
					<div class="form-group has-feedback">
						<input autocomplete="off" id="email" name="email" value="{$email}" type="email"
							   class="form-inline"
							   placeholder="Email"/>
						<span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                        <div class="hint">Type your email address. Email has to be real and accessible or you will not be able to verify it and your account will be deleted after 3 days.</div>
					</div>
					<div class="form-group has-feedback">
						<input id="password" autocomplete="off" name="password" value="{$password}" type="password"
							   class="form-inline" placeholder="Password"/>
						<span class="glyphicon glyphicon-lock form-control-feedback"></span>
						<div class="hint">Your password must be more than 8 characters long, should contain at-least 1 Uppercase, 1 Lowercase, 1 Numeric and 1 special character.</div>
					</div>
					<div class="form-group has-feedback">
						<input autocomplete="off" id="password_confirmation" name="password_confirmation"
							   value="{$confirmpassword}"
							   type="password" class="form-inline" placeholder="Retype password"/>
						<span class="glyphicon glyphicon-log-in form-control-feedback"></span>
                        <div class="hint">Retype your password</div>
					</div>
					<div class="row">
						<div class="col-8">
							<div class="checkbox">
								<label>
									<input type="checkbox"> I agree to the <a
											href="{$serverroot}terms-and-conditions">terms</a>
								</label>
							</div>
						</div><!-- /.col -->
						<div class="col-4">
							{{Form::submit('Register', ['class' => "btn btn-success btn-block btn-flat"])}}
						</div><!-- /.col -->
						<hr>
						<div style="text-align: center;">
                            {if {config('captcha.enabled')} == 1 && !empty({config('captcha.sitekey')}) && !empty({config('captcha.secret')})}
								{NoCaptcha::display()}{NoCaptcha::renderJs()}
							{/if}
						</div>
					</div>
					<a href="{$serverroot}login" class="text-center">I already have a membership</a>
				{{Form::close()}}
			</div><!-- /.form-box -->
		</div>
		<!-- /.register-box -->
	{/if}
</div>

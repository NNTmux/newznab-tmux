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
	{if $confirmed == '' && $sent == ''}
	<div class="login-box">
		<div class="login-logo">
			<a href="{$serverroot}"><b{{config('app.name')}}</b></a>
		</div><!-- /.login-logo -->
		<div class="login-box-body">
			<p class="login-box-msg">Please enter the email address you used to register and we will send an email to
				reset your password. If you
				cannot remember your email, or no longer have access to it, please <a
						href="{{route('contact-us')}}">contact
					us</a>.</p>
            {{Form::open(['url' => 'forgottenpassword?action=submit'])}}
				<div class="form-group has-feedback">
					<input autocomplete="off" id="email" name="email" value="{$email}" type="email" class="form-inline"
						   placeholder="Email"/>
					<span class="glyphicon glyphicon-envelope form-control-feedback"></span>
				</div>
				<div class="form-group has-feedback">
					<input autocomplete="off" id="apikey" name="apikey" value="{$apikey}" type="text"
						   class="form-inline"
						   placeholder="Apikey"/>
					<span class="glyphicon glyphicon-user form-control-feedback"></span>
				</div>
				<div class="row">
					<div class="col-6">
                        {if {config('captcha.enabled')} == 1 && !empty({config('captcha.sitekey')}) && !empty({config('captcha.secret')})}
							{NoCaptcha::display()}{NoCaptcha::renderJs()}
						{/if}
					</div><!-- /.col -->
					<hr>
					<div class="col-12">
						<button type="submit" class="btn btn-success btn-block btn-flat">Request Password Reset</button>
					</div><!-- /.col -->
				</div>
			{{Form::close()}}
			{elseif $sent != ''}
			<p>
				A password reset request has been sent to your email.
			</p>
			{else}
			<p>
				Your password has been reset and sent to you in an email.
			</p>
			{/if}
		</div>
	</div>
</div>

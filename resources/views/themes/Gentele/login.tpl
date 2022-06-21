<div class="card card-body card-header">
	{if Session::has('message')}
		<div class="alert alert-danger">{Session::get('message')}</div>
	{/if}
	<div class="login-box">
		<div class="login-logo">
			<a href="{{url('/')}}"><b>{$site->title}</b></a>
		</div><!-- /.login-logo -->
		<div class="login-box-body">
			<p class="login-box-msg">Please sign in to access the site</p>
			{{Form::open(['url' => 'login', 'id' => 'login'])}}
				<div class="form-group has-feedback">
					{{Form::text('username', null, ['placeholder' => 'Username or email', 'class' => 'form-inline'])}}
					<span class="fas fa-envelope form-control-feedback"></span>
				</div>
				<div class="form-group has-feedback">
					{{Form::password('password', ['class' => 'form-inline'])}}
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
						{{Form::submit('Sign In', ['class' => 'btn btn-success btn-block btn-flat'])}}
					</div><!-- /.col -->
				</div>
			{{Form::close()}}

			<a href="{{route('forgottenpassword')}}" class="text-center">I forgot my password</a><br>
			<a href="{{route('register')}}" class="text-center">Register a new membership</a>
		</div>
	</div>
</div>

<!DOCTYPE html>
<html lang="en">
{if isset($error) && $error != ''}
	<div class="alert alert-danger">{$error}</div>
{/if}
{if isset($notice) && $notice != ''}
	<div class="alert alert-info">{$notice}</div>
{/if}
{if isset($sent) && $sent != ''}
	<div class="alert alert-info">A link to reset your password has been sent to your e-mail account.</div>
{/if}
<head>
	<!--
		===
		This comment should NOT be removed.
		Charisma v2.0.0
		Copyright 2012-2014 Muhammad Usman
		Licensed under the Apache License v2.0
		http://www.apache.org/licenses/LICENSE-2.0
		http://usman.it
		http://twitter.com/halalit_usman
		===
	-->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
{if $confirmed == '' && $sent == ''}
	<div class="ch-container">
		<div class="row">
			<div class="row">
				<div class="col-md-12 center login-header">
					<h2>Request a password reset</h2>
				</div>
				<!--/span-->
			</div>
			<!--/row-->
			<div class="row">
				<div class="well col-md-5 center login-box">
					<div class="alert alert-info">
						Request a password reset
					</div>
					<p class="login-box-msg">Please enter the email address you used to register and we will send an
						email to reset your password. If you
						cannot remember your email, or no longer have access to it, please <a
								href="{$smarty.const.WWW_TOP}/contact-us">contact
							us</a>.</p>
					<form class="form-horizontal" method="post" action="forgottenpassword?action=submit">
						{{csrf_field()}}
						<fieldset>
							<div class="input-group input-group-lg">
								<span class="input-group-addon"><i class="glyphicon glyphicon-envelope red"></i></span>
								<input autocomplete="off" id="email" name="email" value="{$email}" type="email"
									   class="form-control" placeholder="Email"/>
							</div>
							<div class="clearfix"></div>
							<br>
							<div class="input-group input-group-lg">
								<span class="input-group-addon"><i class="glyphicon glyphicon-user red"></i></span>
								<input autocomplete="off" id="apikey" name="apikey" value="{$apikey}" type="text"
									   class="form-control" placeholder="Apikey"/>
							</div>
							<div class="clearfix"></div>
							<br>
							<p class="center col-md-5">
							<p class="center col-md-5">
								{if {env('NOCAPTCHA_ENABLED')} == 1 && !empty({env('NOCAPTCHA_SITEKEY')}) && !empty({env('NOCAPTCHA_SECRET')})}
									{NoCaptcha::display()}{NoCaptcha::renderJs()}
								{/if}
							</p>
							<button type="submit" class="btn btn-primary">Request Password Reset</button>
						</fieldset>
						<a href="{$smarty.const.WWW_TOP}/login" class="text-center">I already have a membership</a>
					</form>
				</div>
				<!--/span-->
			</div>
			<!--/row-->
		</div>
		<!--/fluid-row-->
	</div>
	<!--/.fluid-container-->
{/if}

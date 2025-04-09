<div class="container">
						    <div class="row justify-content-center mt-5">
						        <div class="col-md-6 col-lg-5">
						            <div class="card shadow-sm mb-4">
						                <div class="card-header bg-light">
						                    <h4 class="text-center mb-0">Password Recovery</h4>
						                </div>

						                <div class="card-body p-4">
						                    {if isset($error) && $error != ''}
						                        <div class="alert alert-danger">
						                            <i class="fa fa-exclamation-circle me-2"></i>{$error}
						                        </div>
						                    {/if}

						                    {if isset($notice) && $notice != ''}
						                        <div class="alert alert-info">
						                            <i class="fa fa-info-circle me-2"></i>{$notice}
						                        </div>
						                    {/if}

						                    {if isset($sent) && $sent != ''}
						                        <div class="alert alert-info">
						                            <i class="fa fa-info-circle me-2"></i>A link to reset your password has been sent to your e-mail account.
						                        </div>
						                    {/if}

						                    {if $confirmed == '' && $sent == ''}
						                        <div class="text-center mb-4">
						                            <a href="{{url('/')}}">
						                                <h3 class="mb-0"><b>{{config('app.name')}}</b></h3>
						                            </a>
						                            <p class="text-muted mt-2">Reset your password</p>
						                        </div>

						                        <p class="mb-4">Please enter the email address you used to register and we will send an email to reset your password. If you cannot remember your email, or no longer have access to it, please <a href="{{route('contact-us')}}">contact us</a>.</p>

						                        {{Form::open(['url' => 'forgottenpassword?action=submit'])}}
						                            <div class="mb-3">
						                                <label for="email" class="form-label">Email Address</label>
						                                <div class="input-group">
						                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
						                                    <input autocomplete="off" id="email" name="email" value="{$email}" type="email"
						                                           class="form-control" placeholder="Your registered email"/>
						                                </div>
						                            </div>

						                            <div class="mb-3">
						                                <label for="apikey" class="form-label">API Key <small class="text-muted">(optional)</small></label>
						                                <div class="input-group">
						                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
						                                    <input autocomplete="off" id="apikey" name="apikey" value="{$apikey}" type="text"
						                                           class="form-control" placeholder="Your API key if you have it"/>
						                                </div>
						                                <div class="form-text">Providing your API key helps verify your identity.</div>
						                            </div>

						                            {if {config('captcha.enabled')} == 1 && !empty({config('captcha.sitekey')}) && !empty({config('captcha.secret')})}
						                                <div class="mb-3 d-flex justify-content-center">
						                                    {NoCaptcha::display()}{NoCaptcha::renderJs()}
						                                </div>
						                            {/if}

						                            <div class="d-grid gap-2">
						                                {{Form::submit('Request Password Reset', ['class' => 'btn btn-success'])}}
						                            </div>
						                        {{Form::close()}}
						                    {elseif $sent != ''}
						                        <div class="text-center">
						                            <i class="fa fa-envelope-open fa-3x text-success mb-3"></i>
						                            <h5>Email Sent</h5>
						                            <p class="mb-0">A password reset request has been sent to your email. Please check your inbox and follow the instructions.</p>
						                        </div>
						                    {else}
						                        <div class="text-center">
						                            <i class="fa fa-check-circle fa-3x text-success mb-3"></i>
						                            <h5>Password Reset Complete</h5>
						                            <p class="mb-0">Your password has been reset and sent to you in an email. Please check your inbox for your new credentials.</p>
						                        </div>
						                    {/if}
						                </div>

						                <div class="card-footer bg-light">
						                    <div class="text-center">
						                        <a href="{{url('/login')}}" class="text-decoration-none">
						                            <i class="fa fa-sign-in-alt me-1"></i>Back to login
						                        </a>
						                    </div>
						                </div>
						            </div>
						        </div>
						    </div>
						</div>

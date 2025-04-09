<div class="container">
					    <div class="row justify-content-center mt-5">
					        <div class="col-md-8 col-lg-6">
					            <div class="card shadow-sm mb-4">
					                <div class="card-header bg-light">
					                    <h4 class="text-center mb-0">Create Account</h4>
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

					                    {if $showregister == 1}
					                        <div class="text-center mb-4">
					                            <a href="{{url('/')}}">
					                                <h3 class="mb-0"><b>{{config('app.name')}}</b></h3>
					                            </a>
					                            <p class="text-muted mt-2">Register a new membership</p>
					                        </div>

					                        {{Form::open(['url' => "register?action=submit{$invite_code_query}"])}}
					                            <div class="mb-3">
					                                <label for="username" class="form-label">Username</label>
					                                <div class="input-group">
					                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
					                                    <input autocomplete="off" id="username" name="username" value="{$username}" type="text"
					                                           class="form-control" placeholder="Username"/>
					                                </div>
					                                <div class="form-text">Should be at least five characters and start with a letter.</div>
					                            </div>

					                            <div class="mb-3">
					                                <label for="email" class="form-label">Email</label>
					                                <div class="input-group">
					                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
					                                    <input autocomplete="off" id="email" name="email" value="{$email}" type="email"
					                                           class="form-control" placeholder="Email"/>
					                                </div>
					                                <div class="form-text">Type your email address. Email has to be real and accessible or you will not be able to verify it and your account will be deleted after 3 days.</div>
					                            </div>

					                            <div class="mb-3">
					                                <label for="password" class="form-label">Password</label>
					                                <div class="input-group">
					                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
					                                    <input id="password" autocomplete="off" name="password" value="{$password}" type="password"
					                                           class="form-control" placeholder="Password"/>
					                                </div>
					                                <div class="form-text">Your password must be more than 8 characters long, should contain at-least 1 Uppercase, 1 Lowercase, 1 Numeric and 1 special character.</div>
					                            </div>

					                            <div class="mb-3">
					                                <label for="password_confirmation" class="form-label">Confirm Password</label>
					                                <div class="input-group">
					                                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
					                                    <input autocomplete="off" id="password_confirmation" name="password_confirmation"
					                                           value="{$confirmpassword}" type="password" class="form-control"
					                                           placeholder="Retype password"/>
					                                </div>
					                                <div class="form-text">Retype your password</div>
					                            </div>

					                            <div class="mb-3">
					                                <div class="form-check">
					                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
					                                    <label class="form-check-label" for="terms">
					                                        I agree to the <a href="{{url('/terms-and-conditions')}}">terms and conditions</a>
					                                    </label>
					                                </div>
					                            </div>

					                            {if {config('captcha.enabled')} == 1 && !empty({config('captcha.sitekey')}) && !empty({config('captcha.secret')})}
					                                <div class="mb-3 d-flex justify-content-center">
					                                    {NoCaptcha::display()}{NoCaptcha::renderJs()}
					                                </div>
					                            {/if}

					                            <div class="d-grid gap-2">
					                                {{Form::submit('Register', ['class' => 'btn btn-success'])}}
					                            </div>
					                        {{Form::close()}}
					                    {/if}
					                </div>

					                <div class="card-footer bg-light">
					                    <div class="text-center">
					                        <a href="{{url('/login')}}" class="text-decoration-none">
					                            <i class="fa fa-sign-in-alt me-1"></i>I already have a membership
					                        </a>
					                    </div>
					                </div>
					            </div>
					        </div>
					    </div>
					</div>

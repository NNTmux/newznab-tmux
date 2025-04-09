<div class="container">
                   <div class="row justify-content-center mt-5">
                       <div class="col-md-6 col-lg-5">
                           <div class="card shadow-sm mb-4">
                               <div class="card-header bg-light">
                                   <h4 class="text-center mb-0">Sign In</h4>
                               </div>

                               <div class="card-body p-4">
                                   {if Session::has('message')}
                                       <div class="alert alert-danger">
                                           <i class="fa fa-exclamation-circle me-2"></i>{Session::get('message')}
                                       </div>
                                   {/if}

                                   <div class="text-center mb-4">
                                       <a href="{{url('/')}}">
                                           <h3 class="mb-0"><b>{{config('app.name')}}</b></h3>
                                       </a>
                                       <p class="text-muted mt-2">Please sign in to access the site</p>
                                   </div>

                                   {{Form::open(['url' => 'login', 'id' => 'login'])}}
                                       <div class="mb-3">
                                           <div class="input-group">
                                               <span class="input-group-text"><i class="fas fa-user"></i></span>
                                               {{Form::text('username', null, ['placeholder' => 'Username or email', 'class' => 'form-control'])}}
                                           </div>
                                       </div>

                                       <div class="mb-3">
                                           <div class="input-group">
                                               <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                               {{Form::password('password', ['class' => 'form-control', 'placeholder' => 'Password'])}}
                                           </div>
                                       </div>

                                       <div class="mb-3">
                                           <div class="form-check">
                                               <input id="rememberme" class="form-check-input" {if isset($rememberme) && $rememberme == 1}checked="checked"{/if} name="rememberme" type="checkbox">
                                               <label class="form-check-label" for="rememberme">Remember Me</label>
                                           </div>
                                       </div>

                                       {if {config('captcha.enabled')} == 1 && !empty({config('captcha.sitekey')}) && !empty({config('captcha.secret')})}
                                           <div class="mb-3">
                                               {NoCaptcha::display()}{NoCaptcha::renderJs()}
                                           </div>
                                       {/if}

                                       <div class="d-grid gap-2">
                                           {{Form::submit('Sign In', ['class' => 'btn btn-success'])}}
                                       </div>
                                   {{Form::close()}}
                               </div>

                               <div class="card-footer bg-light">
                                   <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                                       <a href="{{route('forgottenpassword')}}" class="text-decoration-none">
                                           <i class="fa fa-key me-1"></i>Forgot password?
                                       </a>
                                       <a href="{{route('register')}}" class="text-decoration-none mt-2 mt-md-0">
                                           <i class="fa fa-user-plus me-1"></i>Register new account
                                       </a>
                                   </div>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>

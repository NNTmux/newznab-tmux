<!DOCTYPE html>
                                       <html lang="{{App::getLocale()}}">
                                       <head>
                                           <meta charset="utf-8">
                                           <meta name="viewport" content="width=device-width, initial-scale=1">
                                           <meta name="csrf-token" content="{{csrf_token()}}">
                                           <title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
                                           {{Html::style("{{asset('/assets/css/all-css.css')}}")}}
                                       </head>

                                       <body class="login-page">
                                           <div class="container">
                                               <div class="row justify-content-center mt-5">
                                                   <div class="col-md-4 col-lg-3">
                                                       <div class="card shadow-sm mb-4">
                                                           <div class="card-header bg-light">
                                                               <h4 class="text-center mb-0">Sign In</h4>
                                                           </div>

                                                           <div class="card-body p-4">
                                                               {if Session::has('message')}
                                                                   <div class="alert {if Session::has('message_type')}alert-{Session::get('message_type')}{else}alert-info{/if} alert-dismissible fade notification-fade" role="alert">
                                                                       <i class="fa {if Session::has('message_type') && Session::get('message_type') == 'danger'}fa-exclamation-circle{elseif Session::has('message_type') && Session::get('message_type') == 'success'}fa-check-circle{else}fa-info-circle{/if} me-2"></i>
                                                                       {Session::get('message')}
                                                                       <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                                   </div>

                                                                   <style>
                                                                   .notification-fade {
                                                                       opacity: 0;
                                                                       transition: opacity 0.6s ease-in-out;
                                                                   }

                                                                   .notification-fade.show {
                                                                       opacity: 1;
                                                                   }
                                                                   </style>

                                                                   <script>
                                                                   document.addEventListener('DOMContentLoaded', function() {
                                                                       setTimeout(function() {
                                                                           const alerts = document.querySelectorAll('.notification-fade');
                                                                           alerts.forEach(function(alert) {
                                                                               alert.classList.add('show');
                                                                           });
                                                                       }, 100);
                                                                   });
                                                                   </script>
                                                               {/if}

                                                               <div class="text-center mb-4">
                                                                   <a href="{{url('/')}}">
                                                                       <div class="d-flex justify-content-center align-items-center mb-2">
                                                                           <div class="app-logo">
                                                                               <i class="fas fa-file-download" aria-hidden="true"></i>
                                                                           </div>
                                                                           <h3 class="mb-0 ms-2"><b>{{config('app.name')}}</b></h3>
                                                                       </div>
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
                                                                   <div class="d-flex flex-column flex-md-row gap-2">
                                                                       <a href="{{route('forgottenpassword')}}" class="text-decoration-none">
                                                                           <i class="fa fa-key me-1"></i>Forgot password?
                                                                       </a>
                                                                       <a href="{{route('contact-us')}}" class="text-decoration-none">
                                                                           <i class="fa fa-envelope-open-text me-1"></i>Contact Us
                                                                       </a>
                                                                   </div>
                                                                   <a href="{{route('register')}}" class="text-decoration-none mt-2 mt-md-0">
                                                                       <i class="fa fa-user-plus me-1"></i>Register new account
                                                                   </a>
                                                               </div>
                                                           </div>
                                                       </div>
                                                   </div>
                                               </div>
                                           </div>

                                           <!-- jQuery and scripts -->
                                           {{Html::script("{{asset('/assets/js/all-js.js')}}")}}

                                           <style>
                                           .login-page {
                                               background-color: #f8f9fa;
                                               min-height: 100vh;
                                               display: flex;
                                               align-items: center;
                                           }

                                           .app-logo {
                                               background: linear-gradient(135deg, #4e54c8, #8f94fb);
                                               display: inline-flex;
                                               align-items: center;
                                               justify-content: center;
                                               width: 35px;
                                               height: 35px;
                                               border-radius: 8px;
                                               box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                                               transition: all 0.3s ease;
                                           }

                                           .app-logo i {
                                               font-size: 18px;
                                               color: white;
                                           }

                                           a:hover .app-logo {
                                               transform: rotate(5deg);
                                               box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                                           }
                                           </style>
                                       </body>
                                       </html>

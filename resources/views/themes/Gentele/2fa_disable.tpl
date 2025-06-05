<!DOCTYPE html>
<html lang="{{App::getLocale()}}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{csrf_token()}}">
    <title>{$meta_title}{if $meta_title != "" && $site->metatitle != ""} - {/if}{$site->metatitle}</title>
    {{Html::style("{{asset('/assets/css/all-css.css')}}")}}
</head>

<body class="2fa-page">
<div class="container">
    <div class="row justify-content-center mt-4">
        <div class="col-md-8 col-lg-6">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{url("{$site->home_link}")}}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{url("/profile")}}">Profile</a></li>
                    <li class="breadcrumb-item"><a href="{{url("/profileedit")}}">Edit Profile</a></li>
                    <li class="breadcrumb-item active">Disable 2FA</li>
                </ol>
            </nav>

            <!-- 2FA Disable Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h4 class="text-center mb-0"><i class="fa fa-lock-open me-2"></i>Disable Two-Factor Authentication</h4>
                </div>

                <div class="card-body p-4">
                    {if isset($error)}
                        <div class="alert alert-danger notification-fade" role="alert">
                            <i class="fa fa-exclamation-circle me-2"></i>{$error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    {/if}
                    {if isset($success)}
                        <div class="alert alert-success notification-fade" role="alert">
                            <i class="fa fa-check-circle me-2"></i>{$success}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    {/if}

                    <div class="text-center mb-4">
                        <div class="d-flex justify-content-center align-items-center mb-2">
                            <div class="app-icon text-danger">
                                <i class="fas fa-shield-alt fa-3x" aria-hidden="true"></i>
                            </div>
                        </div>
                        <h5 class="mb-1">Disable Two-Factor Authentication</h5>
                        <p class="text-muted">Remove the extra security layer from your account</p>
                    </div>

                    {if $user->passwordSecurity && $user->passwordSecurity->google2fa_enable}
                        <div class="alert alert-warning mb-4">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> Disabling 2FA will make your account less secure. Only proceed if absolutely necessary.
                        </div>

                        <div class="card bg-light border-0 p-4 mb-4">
                            <h6 class="mb-3 text-center">Confirm Password to Disable 2FA</h6>
                            <p class="text-center text-muted mb-4">Please enter your current password to verify your identity:</p>
                            <form action="{{url('/profileedit/disable2fa')}}" method="POST" autocomplete="off">
                                {{csrf_field()}}
                                <input type="hidden" name="redirect_to_profile" value="1">
                                <div class="mb-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input id="current-password" type="password" class="form-control" name="current-password" required placeholder="Current Password" autocomplete="off">
                                    </div>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fa fa-times-circle me-2"></i>Disable 2FA
                                    </button>
                                    <a href="{{url("/profileedit#security")}}" class="btn btn-outline-secondary">
                                        <i class="fa fa-arrow-left me-2"></i>Cancel and Go Back
                                    </a>
                                </div>
                            </form>
                        </div>
                    {else}
                        <div class="alert alert-info mb-4">
                            <i class="fa fa-info-circle me-2"></i>Two-factor authentication is not currently enabled on your account.
                        </div>
                        <div class="text-center">
                            <a href="{{url("/profileedit#security")}}" class="btn btn-primary">
                                <i class="fa fa-arrow-left me-2"></i>Back to Profile
                            </a>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>
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
</body>
</html>

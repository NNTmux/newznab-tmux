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
                    <li class="breadcrumb-item active">Enable 2FA</li>
                </ol>
            </nav>

            <!-- 2FA Setup Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h4 class="text-center mb-0"><i class="fa fa-lock me-2"></i>Enable Two-Factor Authentication</h4>
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
                            <div class="app-icon text-primary">
                                <i class="fas fa-shield-alt fa-3x" aria-hidden="true"></i>
                            </div>
                        </div>
                        <h5 class="mb-1">Two-Factor Authentication Setup</h5>
                        <p class="text-muted">Add an extra layer of security to your account</p>
                    </div>

                    <div class="alert alert-info">
                        <i class="fa fa-info-circle me-2"></i>Two factor authentication (2FA) strengthens access security by requiring two methods to verify your identity. It protects against phishing, social engineering, and password brute force attacks.
                    </div>

                    {if !isset($user->passwordSecurity)}
                        <div class="text-center my-4">
                            <form action="{{url("generate2faSecret")}}" method="POST">
                                {{csrf_field()}}
                                <input type="hidden" name="redirect_to_setup" value="1">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-key me-2"></i>Generate Secret Key to Begin Setup
                                    </button>
                                </div>
                            </form>
                        </div>
                    {elseif !$user->passwordSecurity->google2fa_enable}
                        <div class="row mb-4">
                            <div class="col-md-6 mb-4 mb-md-0">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body text-center">
                                        <h6 class="mb-3">1. Scan this QR code with your Google Authenticator app:</h6>
                                        <div class="mb-3 qr-container p-3 bg-white rounded d-inline-block">
                                            <img src="{$google2fa_url}" alt="2FA QR Code" class="img-fluid">
                                        </div>
                                        <p class="text-muted small">If you can't scan the QR code, please set up manually using the code provided.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light border-0">
                                    <div class="card-body">
                                        <h6 class="mb-3">2. Enter the verification code from your app:</h6>
                                        <form action="{{url('/profileedit/enable2fa')}}" method="POST" autocomplete="off">
                                            {{csrf_field()}}
                                            <input type="hidden" name="redirect_to_profile" value="1">
                                            <div class="mb-4">
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                                    <input id="verify-code" type="text" class="form-control" name="verify-code" required placeholder="Enter 6-digit code" autocomplete="off">
                                                </div>
                                                <div class="form-text">Enter the 6-digit code from your authenticator app</div>
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fa fa-check-circle me-2"></i>Verify and Enable 2FA
                                                </button>
                                                <a href="{{url("/profileedit#security")}}" class="btn btn-outline-secondary">
                                                    <i class="fa fa-arrow-left me-2"></i>Back to Profile
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Important:</strong> Store your backup codes in a secure location. If you lose your device, you will need these codes to regain access to your account.
                        </div>
                    {elseif $user->passwordSecurity->google2fa_enable}
                        <div class="alert alert-success mb-4">
                            <i class="fa fa-check-circle me-2"></i>Two-factor authentication is currently <strong>enabled</strong> for your account.
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

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
                        <h4 class="text-center mb-0">Two-Factor Authentication</h4>
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
                            <p class="text-muted mt-2">Please enter your one-time verification code</p>
                        </div>

                        {{Form::open(['url' => route('2faVerify'), 'id' => '2faVerify'])}}
                            <div class="mb-3">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <input type="text" class="form-control text-center otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required>
                                    <input type="text" class="form-control text-center otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required>
                                    <input type="text" class="form-control text-center otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required>
                                    <input type="text" class="form-control text-center otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required>
                                    <input type="text" class="form-control text-center otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required>
                                    <input type="text" class="form-control text-center otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" required>
                                </div>
                                <input id="one_time_password" type="hidden" name="one_time_password" required>
                                <div class="form-text text-muted">
                                    Enter the 6-digit code from your authentication app
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success" id="verify-button">
                                    <i class="fa fa-check-circle me-2"></i>Verify
                                </button>
                            </div>
                        {{Form::close()}}
                    </div>

                    <div class="card-footer bg-light">
                        <div class="text-center">
                            <p class="text-muted small mb-0">
                                If you're having trouble, please contact the administrator
                            </p>
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

    .otp-input {
        width: 45px;
        height: 45px;
        font-size: 1.2rem;
        font-weight: bold;
    }

    @media (max-width: 576px) {
        .otp-input {
            width: 40px;
            height: 40px;
            font-size: 1.1rem;
        }
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle OTP input functionality
        const otpInputs = document.querySelectorAll('.otp-input');
        const verifyCodeInput = document.getElementById('one_time_password');
        const verifyButton = document.getElementById('verify-button');

        if (otpInputs.length > 0) {
            // Set focus on first input on page load
            otpInputs[0].focus();

            // Add event listeners to each OTP input
            otpInputs.forEach(function(input, index) {
                // Handle number input and auto-focus
                input.addEventListener('input', function(e) {
                    const value = e.target.value;

                    // Only allow digits
                    if (/^\d*$/.test(value)) {
                        // Auto advance to next input
                        if (value.length === 1 && index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                        }

                        // Combine all inputs into the hidden field
                        updateHiddenField();
                    } else {
                        // Clear non-numeric input
                        e.target.value = '';
                    }
                });

                // Handle backspace key
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        // Move to previous input when backspace is pressed on empty input
                        otpInputs[index - 1].focus();
                    }
                });

                // Handle paste event (allow pasting full 6-digit code)
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                    const digits = pasteData.replace(/\D/g, '').substring(0, 6).split('');

                    if (digits.length > 0) {
                        // Fill inputs with pasted digits
                        otpInputs.forEach((input, i) => {
                            if (i < digits.length) {
                                input.value = digits[i];
                            }
                        });

                        // Focus on appropriate field
                        if (digits.length >= 6) {
                            otpInputs[5].focus();
                        } else {
                            otpInputs[Math.min(digits.length, 5)].focus();
                        }

                        // Update hidden field
                        updateHiddenField();
                    }
                });
            });

            // Submit form on full code entry
            function updateHiddenField() {
                const code = Array.from(otpInputs).map(input => input.value).join('');
                verifyCodeInput.value = code;

                // Auto submit if all 6 digits are entered
                if (code.length === 6 && /^\d{6}$/.test(code)) {
                    setTimeout(() => {
                        verifyButton.classList.add('btn-pulse');
                    }, 200);
                }
            }
        }
    });
    </script>
</body>
</html>

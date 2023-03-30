@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Two Factor Authentication</strong></div>
                    <div class="panel-body">
                        <p>Two factor authentication (2FA) strengthens access security by requiring two methods (also referred to as factors) to verify your identity. Two factor authentication protects against phishing, social engineering and password brute force attacks and secures your logins from attackers exploiting weak or stolen credentials.</p>
                        <br/>
                        <p>To Enable Two Factor Authentication on your Account, you need to do following steps</p>
                        <strong>
                            <ol>
                                <li>Click on Generate Secret Button , To Generate a Unique secret QR code for your profile</li>
                                <li>Verify the OTP from Google Authenticator Mobile App</li>
                            </ol>
                        </strong>
                        <br/>

                        @if (session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif
                        @if (session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif


                        @if(!($data['user']->passwordSecurity))
                            <form class="form-horizontal" method="POST" action="{{ route('generate2faSecret') }}">
                                @csrf
                                <div class="form-group">
                                    <div class="col-md-6 col-md-offset-4">
                                        <button type="submit" class="btn btn-primary">
                                            Generate Secret Key to Enable 2FA
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @elseif(!$data['user']->passwordSecurity->google2fa_enable)
                            <strong>1. Scan this barcode with your Google Authenticator App:</strong><br/>
                            <img src="{{$data['google2fa_url'] }}" alt="">
                            <br/><br/>
                            <strong>2.Enter the pin the code to Enable 2FA</strong><br/><br/>
                            <form class="form-horizontal" method="POST" action="{{ route('enable2fa') }}">
                                @csrf

                                <div class="form-group{{ $errors->has('verify-code') ? ' has-error' : '' }}">
                                    <label for="verify-code" class="col-md-4 control-label">Authenticator Code</label>

                                    <div class="col-md-6">
                                        <input id="verify-code" type="password" class="form-control" name="verify-code" required>

                                        @if ($errors->has('verify-code'))
                                            <span class="help-block">
                                        <strong>{{ $errors->first('verify-code') }}</strong>
                                    </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="col-md-6 col-md-offset-4">
                                        <button type="submit" class="btn btn-primary">
                                            Enable 2FA
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @elseif($data['user']->passwordSecurity->google2fa_enable)
                            <div class="alert alert-success">
                                2FA is Currently <strong>Enabled</strong> for your account.
                            </div>
                            <p>If you are looking to disable Two Factor Authentication. Please confirm your password and Click Disable 2FA Button.</p>
                            <form class="form-horizontal" method="POST" action="{{ route('disable2fa') }}">
                                <div class="form-group{{ $errors->has('current-password') ? ' has-error' : '' }}">
                                    <label for="change-password" class="col-md-4 control-label">Current Password</label>

                                    <div class="col-md-6">
                                        <input id="current-password" type="password" class="form-control" name="current-password" required>

                                        @if ($errors->has('current-password'))
                                            <span class="help-block">
                                        <strong>{{ $errors->first('current-password') }}</strong>
                                    </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6 col-md-offset-5">

                                    @csrf
                                    <button type="submit" class="btn btn-primary ">Disable 2FA</button>
                                </div>
                            </form>
                            @endif
                            </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

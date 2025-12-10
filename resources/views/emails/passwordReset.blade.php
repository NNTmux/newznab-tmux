@extends('emails.email_layout')

@section('title')
    Password Reset Successful
@endsection

@section('site_name', $site)

@section('content')
    <p class="greeting">Dear {{ $userName }},</p>

    <p>Your password has been successfully reset.</p>

    <div class="info-box">
        <strong>🔑 Your New Password:</strong>
        <div style="margin-top: 10px; font-family: monospace; font-size: 18px; letter-spacing: 2px;">{{ $newPass }}</div>
    </div>

    <div class="warning-box">
        <strong>🔒 Security Recommendation:</strong> For your security, we strongly recommend changing this password to something memorable after logging in.
    </div>

    <p>If you did not request this password reset, please contact us immediately.</p>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} Team</strong></p>
    </div>
@endsection

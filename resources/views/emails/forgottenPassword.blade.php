@extends('emails.email_layout')

@section('title')
    Password Reset Request
@endsection

@section('site_name', $site)

@section('content')
    <p class="greeting">Hello,</p>

    <p>We received a request to reset the password associated with this email address.</p>

    <div style="text-align: center;">
        <a href="{{ $resetLink }}" class="button">Reset Password</a>
    </div>

    <p>Or copy and paste this link into your browser:</p>
    <span class="link-text">{{ $resetLink }}</span>

    <div class="warning-box">
        <strong>🔒 Security Notice:</strong> If you didn't request this password reset, you can safely ignore this email. Your password will remain unchanged.
    </div>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} Team</strong></p>
    </div>
@endsection

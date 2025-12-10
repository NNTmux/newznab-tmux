@extends('emails.email_layout')

@section('title')
    Welcome to {{ $site }}
@endsection

@section('site_name', $site)

@section('content')
    <p class="greeting">Dear {{ $username }},</p>

    <p>Welcome to <strong>{{ $site }}</strong>! We're thrilled to have you join our community.</p>

    <div class="info-box">
        <strong>📧 Next Step:</strong> Your account verification email will be sent separately. Please check your inbox and verify your email address to complete the registration process.
    </div>

    <p>If you have any questions or need assistance, don't hesitate to reach out to us.</p>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} Team</strong></p>
    </div>
@endsection

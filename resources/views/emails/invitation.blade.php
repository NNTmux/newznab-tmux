@extends('emails.email_layout')

@section('title')
    You're Invited to {{ $siteName }}!
@endsection

@section('site_name', $siteName)

@section('content')
    <p class="greeting">Hello!</p>

    <p>Great news! <strong>{{ $invitedBy->username }}</strong> has invited you to join <strong>{{ $siteName }}</strong>, our exclusive community.</p>

    <div style="text-align: center;">
        <a href="{{ $registerUrl }}" class="button">Accept Invitation</a>
    </div>

    <p>Or copy and paste this link into your browser:</p>
    <span class="link-text">{{ $registerUrl }}</span>

    <div class="warning-box">
        <strong>⚠️ Important:</strong> This invitation will expire on <strong>{{ $expiresAt->format('F j, Y \a\t g:i A') }}</strong>. Please complete your registration before this date.
    </div>

    <div class="info-box">
        <strong>💡 What's Next?</strong> Click the button above to create your account and start exploring everything {{ $siteName }} has to offer.
    </div>

    <p>If you have any questions, please don't hesitate to contact us after registering.</p>

    <div class="signature">
        <p>Welcome aboard!</p>
        <p><strong>The {{ $siteName }} Team</strong></p>
    </div>
@endsection

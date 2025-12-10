@extends('emails.email_layout')

@section('title')
    You've Been Invited!
@endsection

@section('site_name', $site)

@section('content')
    <p class="greeting">Hello {{ $email }},</p>

    <p>Great news! <strong>{{ $username }}</strong> has invited you to join <strong>{{ $site }}</strong>.</p>

    <div style="text-align: center;">
        <a href="{{ $invite }}" class="button">Accept Invitation</a>
    </div>

    <p>Or copy and paste this link into your browser:</p>
    <span class="link-text">{{ $invite }}</span>

    <div class="info-box">
        <strong>💡 What's Next?</strong> Click the button above to create your account and start exploring everything {{ $site }} has to offer.
    </div>

    <p>If you have any questions, feel free to reach out to us after registering.</p>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} Team</strong></p>
    </div>
@endsection

@extends('emails.email_layout')

@section('title')
    Account Expired
@endsection

@section('site_name', $site)

@section('content')
    <p class="greeting">Dear {{ $username }},</p>

    <p>We regret to inform you that your account subscription has expired.</p>

    <div class="warning-box">
        <strong>📉 Account Downgraded:</strong> Your account has been automatically downgraded to <strong>{{ $account }}</strong>.
    </div>

    <p>Don't worry – you can still access the site with your downgraded account level. If you'd like to regain access to all your previous features and benefits, please consider renewing your subscription.</p>

    <p>If you have any questions or need assistance with the renewal process, please don't hesitate to contact us.</p>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} Team</strong></p>
    </div>
@endsection

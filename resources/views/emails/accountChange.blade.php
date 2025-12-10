@extends('emails.email_layout')

@section('title')
    Account Level Changed
@endsection

@section('site_name', $site)

@section('content')
    <p class="greeting">Dear {{ $username }},</p>

    <p>We wanted to let you know that your account level has been updated.</p>

    <div class="info-box">
        <strong>🔄 New Account Level:</strong> {{ $account }}
    </div>

    <p>Your new account level is now active, and you can start enjoying all the associated features and benefits immediately.</p>

    <p>If you have any questions about your new account level or need assistance, please don't hesitate to contact us.</p>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} Team</strong></p>
    </div>
@endsection

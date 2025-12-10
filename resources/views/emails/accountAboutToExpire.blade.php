@extends('emails.email_layout')

@section('title')
    Account Expiration Notice
@endsection

@section('site_name', $site)

@section('content')
    <p class="greeting">Dear {{ $username }},</p>

    <div class="warning-box">
        <strong>⚠️ Attention Required:</strong> Your account role <strong>{{ $account }}</strong> is about to expire in less than <strong>{{ $days }} day(s)</strong>.
    </div>

    <p>To continue enjoying uninterrupted access to all your current features and benefits, please take action before your subscription expires.</p>

    <p>If you have any questions about renewing your account or need assistance, please don't hesitate to contact us.</p>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} Team</strong></p>
    </div>
@endsection

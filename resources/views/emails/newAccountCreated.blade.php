@extends('emails.email_layout')

@section('title')
    New User Registration
@endsection

@section('site_name', $site)

@section('content')
    <p>A new user has registered on <strong>{{ $site }}</strong>.</p>

    <div class="info-box">
        <strong>👤 New User:</strong> {{ $username }}
    </div>

    <p>This is an automated notification. No action is required unless you need to review the new registration.</p>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} System</strong></p>
    </div>
@endsection

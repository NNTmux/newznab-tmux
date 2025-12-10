@extends('emails.email_layout')

@section('title')
    User Account Deleted
@endsection

@section('site_name', $site)

@section('content')
    <p>This is a notification that a user has deleted their account from <strong>{{ $site }}</strong>.</p>

    <div class="info-box">
        <strong>👤 Deleted Account:</strong> {{ $username }}
    </div>

    <p>No further action is required. This email is for informational purposes only.</p>

    <div class="signature">
        <p>Best regards,</p>
        <p><strong>The {{ $site }} System</strong></p>
    </div>
@endsection

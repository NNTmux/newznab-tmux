@extends('emails.email_layout')

@section('title')
    Contact Form Submission
@endsection

@section('content')
    <p>A new contact form has been submitted.</p>

    <div class="info-box">
        <strong>📝 Message:</strong>
        <div style="margin-top: 10px;">
            {!! nl2br(e($mailBody)) !!}
        </div>
    </div>

    <p>Please respond to this inquiry at your earliest convenience.</p>
@endsection

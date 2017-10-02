@extends('emails.email_layout')

@section('title')
    Forgotten Password
@endsection

@section('content')

    Someone has requested a password reset for this email address.
    <br>
    To reset the password use the following link: <a href="{{$resetLink}}">{{$resetLink}}</a><br>
    <br><br><br>
    Greetings from {{ $site }}
@endsection

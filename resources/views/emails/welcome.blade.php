@extends('emails.email_layout')

@section('title')
    Welcome to {{ $site }}
@endsection

@section('content')
    Dear {{ $username }},
    <br>
    Welcome to our site. Your account verification will be sent in separate email.
    <br><br><br>
    Greetings from {{ $site }}
@endsection

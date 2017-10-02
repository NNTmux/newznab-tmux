@extends('emails.email_layout')

@section('title')
    Forgotten Password
@endsection

@section('content')
    Dear {{ $userName }},
    <br>
    Your password has been reset to: {{ $newPass }}
    <br><br><br>
    Greetings from {{ $site }}
@endsection
